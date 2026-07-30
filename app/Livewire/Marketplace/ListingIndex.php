<?php

namespace App\Livewire\Marketplace;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Listing;
use App\Models\Category;

class ListingIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $selectedCategory = null;
    public string $conditionFilter = '';
    public string $sortBy = 'latest';

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => null, 'as' => 'category'],
        'conditionFilter' => ['except' => '', 'as' => 'condition'],
        'sortBy' => ['except' => 'latest'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->selectedCategory = ($this->selectedCategory === $categoryId) ? null : $categoryId;
        $this->resetPage();
    }

    public function setCondition(string $condition): void
    {
        $this->conditionFilter = ($this->conditionFilter === $condition) ? '' : $condition;
        $this->resetPage();
    }

    public function render()
    {
        $query = Listing::query()
            ->with(['seller', 'category'])
            ->where('status', 'active');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if (!empty($this->conditionFilter)) {
            $query->where('condition', $this->conditionFilter);
        }

        if ($this->sortBy === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($this->sortBy === 'price_desc') {
            $query->orderBy('price', 'desc');
        } else {
            $query->latest();
        }

        $listings = $query->paginate(9);
        $categories = Category::withCount(['listings' => fn($q) => $q->where('status', 'active')])->get();

        return view('livewire.marketplace.listing-index', [
            'listings' => $listings,
            'categories' => $categories,
        ])->layout('layouts.app', ['title' => 'Explore Campus Marketplace - UniMarket']);
    }
}
