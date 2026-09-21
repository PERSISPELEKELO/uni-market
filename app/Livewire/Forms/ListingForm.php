<?php

namespace App\Livewire\Forms;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class ListingForm extends Form
{
    public const MAX_IMAGES = 4;

    public ?int $category_id = null;

    public string $title = '';

    public string $description = '';

    public string $price = '';

    public string $condition = 'good';

    /**
     * Photos already stored for the listing (edit only), as public-disk paths.
     *
     * @var array<int, string>
     */
    public array $existing_images = [];

    /**
     * Newly uploaded photos that have not been saved yet.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $images = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $remainingSlots = max(0, self::MAX_IMAGES - count($this->existing_images));

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:15', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0.5', 'max:999999.99'],
            'condition' => ['required', Rule::in(array_keys(Listing::CONDITIONS))],
            'images' => [
                Rule::requiredIf(count($this->existing_images) === 0),
                'array',
                'max:'.$remainingSlots,
            ],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Please choose a category.',
            'category_id.exists' => 'Please choose one of the listed categories.',
            'title.required' => 'Please give your item a title.',
            'title.min' => 'The title should be at least 5 characters so buyers can find it.',
            'title.max' => 'The title is too long. Keep it under 150 characters.',
            'description.required' => 'Please describe your item.',
            'description.min' => 'Add a little more detail - at least 15 characters.',
            'description.max' => 'The description is too long. Keep it under 5,000 characters.',
            'price.required' => 'Please enter a price.',
            'price.numeric' => 'The price must be a number, for example 250 or 249.50.',
            'price.min' => 'The price must be at least K0.50.',
            'price.max' => 'The price cannot be more than K999,999.99.',
            'condition.required' => 'Please select the condition of your item.',
            'condition.in' => 'Please select one of the listed conditions.',
            'images.required' => 'Please upload at least one photo of your item.',
            'images.max' => 'You can have at most '.self::MAX_IMAGES.' photos per listing.',
            'images.*.image' => 'Each photo must be an image file (JPG, PNG or WebP).',
            'images.*.mimes' => 'Photos must be JPG, PNG or WebP files.',
            'images.*.max' => 'Each photo must be 3 MB or smaller.',
            'images.*.uploaded' => 'A photo failed to upload. Please try again with a smaller file.',
        ];
    }

    public function fillFromListing(Listing $listing): void
    {
        $this->category_id = $listing->category_id;
        $this->title = $listing->title;
        $this->description = $listing->description;
        $this->price = (string) $listing->price;
        $this->condition = $listing->condition;
        $this->existing_images = array_values($listing->images ?? []);
        $this->images = [];
    }

    public function removeExistingImage(int $index): void
    {
        unset($this->existing_images[$index]);
        $this->existing_images = array_values($this->existing_images);
    }

    public function removeNewImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function store(User $seller): Listing
    {
        $this->validate();

        $paths = $this->storeNewImages();

        try {
            return $seller->listings()->create($this->listingAttributes($paths) + ['status' => Listing::STATUS_ACTIVE]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);

            throw $exception;
        }
    }

    public function update(Listing $listing): Listing
    {
        $this->validate();

        $originalImages = $listing->images ?? [];
        $paths = array_merge($this->existing_images, $this->storeNewImages());

        try {
            DB::transaction(fn () => $listing->update($this->listingAttributes($paths)));
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_diff($paths, $originalImages));

            throw $exception;
        }

        Storage::disk('public')->delete(array_diff($originalImages, $paths));

        return $listing;
    }

    /**
     * @param  array<int, string>  $imagePaths
     * @return array<string, mixed>
     */
    private function listingAttributes(array $imagePaths): array
    {
        return [
            'category_id' => $this->category_id,
            'title' => trim($this->title),
            'description' => trim($this->description),
            'price' => $this->price,
            'condition' => $this->condition,
            'images' => $imagePaths,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function storeNewImages(): array
    {
        return collect($this->images)
            ->map(fn (TemporaryUploadedFile $image): string => $image->store('listings', 'public'))
            ->values()
            ->all();
    }
}
