<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Models\Category;
use App\Models\Listing;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('has exactly the 15 canonical English categories, with no placeholder names', function () {
    $names = Category::pluck('name')->sort()->values()->all();

    expect($names)->toBe([
        'Accommodation',
        'Beauty & Personal Care',
        'Bicycles & Campus Transport',
        'Books & Textbooks',
        'Clothing & Fashion',
        'Dormitory & Appliances',
        'Electronics & Laptops',
        'Food & Beverages',
        'Furniture',
        'Other',
        'Phones & Accessories',
        'Services',
        'Shoes & Footwear',
        'Sports & Fitness',
        'Stationery & School Supplies',
    ]);

    foreach (['Beatae Et', 'Et Rem', 'Explicabo Odit', 'Impedit Quia', 'Quod Consequatur', 'Velit Ut'] as $placeholder) {
        expect($names)->not->toContain($placeholder);
    }
});

it('configures the transaction mode for each category exactly as specified', function () {
    $expected = [
        'Electronics & Laptops' => 'INSPECTION',
        'Phones & Accessories' => 'INSPECTION',
        'Books & Textbooks' => 'DIRECT',
        'Clothing & Fashion' => 'DIRECT',
        'Shoes & Footwear' => 'DIRECT',
        'Dormitory & Appliances' => 'INSPECTION',
        'Furniture' => 'INSPECTION',
        'Bicycles & Campus Transport' => 'INSPECTION',
        'Food & Beverages' => 'DIRECT',
        'Beauty & Personal Care' => 'DIRECT',
        'Sports & Fitness' => 'DIRECT',
        'Stationery & School Supplies' => 'DIRECT',
        'Services' => 'DIRECT',
        'Accommodation' => 'INSPECTION',
        'Other' => 'INSPECTION',
    ];

    $actual = Category::pluck('transaction_mode', 'name')->all();

    expect($actual)->toBe($expected);

    foreach ($expected as $name => $mode) {
        $category = Category::where('name', $name)->first();

        expect($category->isDirect())->toBe($mode === 'DIRECT')
            ->and($category->isInspection())->toBe($mode === 'INSPECTION');
    }
});

it('shows real listing counts in the category filter, never hard-coded', function () {
    $food = Category::where('slug', 'food-beverages')->first();
    Listing::factory()->count(3)->create(['category_id' => $food->id, 'status' => 'active']);
    Listing::factory()->create(['category_id' => $food->id, 'status' => 'sold']); // not active - must not count

    Livewire::test(ListingIndex::class)
        ->assertViewHas('categories', function ($categories) use ($food) {
            return $categories->firstWhere('id', $food->id)->listings_count === 3;
        });
});
