<?php

namespace App\Livewire\Marketplace;

use App\Models\Category;
use App\Models\Listing;
use Livewire\Component;
use Livewire\WithPagination;

class ListingIndex extends Component
{
    use WithPagination;

    private const SORTS = ['latest', 'price_asc', 'price_desc'];

    public string $search = '';

    public ?int $selectedCategory = null;

    public string $conditionFilter = '';

    public string $sortBy = 'latest';

    public bool $hotDealsOnly = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => null, 'as' => 'category'],
        'conditionFilter' => ['except' => '', 'as' => 'condition'],
        'sortBy' => ['except' => 'latest'],
        'hotDealsOnly' => ['except' => false, 'as' => 'deals'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->selectedCategory = ($this->selectedCategory === $categoryId) ? null : $categoryId;
        $this->resetPage();
    }

    public function toggleHotDeals(): void
    {
        $this->hotDealsOnly = ! $this->hotDealsOnly;
        $this->resetPage();
    }

    public function setCondition(string $condition): void
    {
        $this->conditionFilter = ($this->conditionFilter === $condition) ? '' : $condition;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'selectedCategory', 'conditionFilter', 'sortBy', 'hotDealsOnly');
        $this->resetPage();
    }

    public function render()
    {
        $condition = array_key_exists($this->conditionFilter, Listing::CONDITIONS) ? $this->conditionFilter : '';
        $sortBy = in_array($this->sortBy, self::SORTS, true) ? $this->sortBy : 'latest';

        $listings = Listing::query()
            ->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])
            ->withCount(['reservations as active_reservations_count' => fn ($query) => $query->active()])
            ->active()
            ->search($this->search)
            ->when($this->selectedCategory, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($condition !== '', fn ($query) => $query->where('condition', $condition))
            ->when($this->hotDealsOnly, fn ($query) => $query->hotDeals())
            ->when($sortBy === 'price_asc', fn ($query) => $query->orderBy('price'))
            ->when($sortBy === 'price_desc', fn ($query) => $query->orderByDesc('price'))
            ->when($sortBy === 'latest', fn ($query) => $query->latest())
            ->paginate(12);

        $categories = Category::withCount(['listings' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        $hasActiveFilters = $this->search !== '' || $this->selectedCategory !== null || $condition !== '' || $sortBy !== 'latest' || $this->hotDealsOnly;

        return view('livewire.marketplace.listing-index', [
            'listings' => $listings,
            'categories' => $categories,
            'hasActiveFilters' => $hasActiveFilters,
            // Only teased on the unfiltered homepage - once someone is filtering
            // or has opted into "hotDealsOnly", the main grid already shows them.
            'hotDeals' => $hasActiveFilters ? collect() : Listing::active()->hotDeals()->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])->latest('price_dropped_at')->limit(8)->get(),
        ])->layout('layouts.app', ['title' => 'Campus Marketplace - UniMarket']);
    }
}
