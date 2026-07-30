<?php

namespace App\Livewire\Marketplace;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Listing;
use App\Models\Category;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class CreateListing extends Component
{
    use WithFileUploads;

    public ?int $category_id = null;
    public string $title = '';
    public string $description = '';
    public string $price = '';
    public string $condition = 'good';
    public array $images = [];

    protected function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|min:5|max:150',
            'description' => 'required|string|min:15',
            'price' => 'required|numeric|min:0.50|max:999999.99',
            'condition' => 'required|in:new,like_new,good,fair',
            'images.*' => 'image|max:3072', // 3MB max per image
            'images' => 'required|array|min:1|max:4',
        ];
    }

    protected array $messages = [
        'images.required' => 'Please upload at least one image of your item.',
        'images.max' => 'You can upload a maximum of 4 images per listing.',
    ];

    public function removeImage(int $index): void
    {
        array_splice($this->images, $index, 1);
    }

    public function save()
    {
        $this->validate();

        $imagePaths = [];
        foreach ($this->images as $image) {
            $imagePaths[] = $image->store('listings', 'public');
        }

        $listing = Listing::create([
            'user_id' => Auth::id(),
            'category_id' => $this->category_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'condition' => $this->condition,
            'images' => $imagePaths,
            'status' => 'active',
        ]);

        // Log Audit Trail
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'LISTING_CREATED',
            'entity_type' => Listing::class,
            'entity_id' => $listing->id,
            'payload' => [
                'title' => $listing->title,
                'price' => $listing->price,
                'category_id' => $listing->category_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('listings.show', $listing->id)
            ->with('success', 'Your listing has been published to the campus marketplace!');
    }

    public function render()
    {
        return view('livewire.marketplace.create-listing', [
            'categories' => Category::all(),
        ])->layout('layouts.app', ['title' => 'Post New Item - UniMarket']);
    }
}
