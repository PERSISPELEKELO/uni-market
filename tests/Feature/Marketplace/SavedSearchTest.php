<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Models\Category;
use App\Models\SavedSearch;
use App\Models\User;
use Livewire\Livewire;

it('saves the current search term, category and condition for a signed-in student', function () {
    $student = User::factory()->create();
    $category = Category::factory()->create();

    Livewire::actingAs($student)->test(ListingIndex::class)
        ->set('search', 'calculator')
        ->set('selectedCategory', $category->id)
        ->set('conditionFilter', 'new')
        ->call('saveSearch');

    expect(SavedSearch::where('user_id', $student->id)->count())->toBe(1);
    $saved = SavedSearch::where('user_id', $student->id)->first();
    expect($saved->term)->toBe('calculator')
        ->and($saved->category_id)->toBe($category->id)
        ->and($saved->condition)->toBe('new');
});

it('does not save an empty search with no filters at all', function () {
    $student = User::factory()->create();

    Livewire::actingAs($student)->test(ListingIndex::class)->call('saveSearch');

    expect(SavedSearch::count())->toBe(0);
});

it('does not create a duplicate for the exact same saved search', function () {
    $student = User::factory()->create();

    Livewire::actingAs($student)->test(ListingIndex::class)
        ->set('search', 'calculator')
        ->call('saveSearch')
        ->call('saveSearch');

    expect(SavedSearch::where('user_id', $student->id)->count())->toBe(1);
});

it('sends a guest to log in instead of saving anything', function () {
    Livewire::test(ListingIndex::class)
        ->set('search', 'calculator')
        ->call('saveSearch')
        ->assertRedirect(route('login'));

    expect(SavedSearch::count())->toBe(0);
});
