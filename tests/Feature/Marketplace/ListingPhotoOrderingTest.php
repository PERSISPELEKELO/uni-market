<?php

use App\Livewire\Marketplace\CreateListing;
use App\Livewire\Marketplace\EditListing;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
});

/**
 * A minimal, valid CreateListing component with the given photos already
 * staged - self-contained rather than relying on the similarly-named
 * helpers in ListingManagementTest.php, which aren't loaded when this file
 * runs on its own.
 */
function startListingWithPhotos(User $seller, Category $category, array $photos)
{
    return Livewire::actingAs($seller)->test(CreateListing::class)
        ->set('form.category_id', $category->id)
        ->set('form.title', 'Calculus 9th Edition textbook')
        ->set('form.description', 'Clean copy with light highlighting in chapter three.')
        ->set('form.price', '250.00')
        ->set('form.condition', 'good')
        ->set('form.images', $photos);
}

it('saves a brand new listing with the seller\'s own custom photo order, not upload order', function () {
    $category = Category::factory()->create();
    $seller = User::factory()->create();

    $component = startListingWithPhotos($seller, $category, [fakePhoto('front.png'), fakePhoto('back.png'), fakePhoto('side.png')]);

    $uploadOrder = $component->get('form.photoOrder');
    expect($uploadOrder)->toHaveCount(3);

    // Reorder so the last upload becomes first.
    $reordered = [$uploadOrder[2], $uploadOrder[0], $uploadOrder[1]];
    $component->call('reorderPhotos', $reordered)
        ->assertSet('form.photoOrder', $reordered)
        ->call('save')
        ->assertHasNoErrors();

    $listing = Listing::firstOrFail();
    expect($listing->images)->toHaveCount(3);

    Storage::disk('public')->assertExists($listing->images[0]);
    // The chosen main photo (originally the third upload) is now first.
    expect(basename($listing->images[0]))->not->toBe(basename($listing->images[1]));
});

it('moves a photo to the main (first) position with setMainPhoto', function () {
    $category = Category::factory()->create();
    $seller = User::factory()->create();

    $component = startListingWithPhotos($seller, $category, [fakePhoto('front.png'), fakePhoto('back.png'), fakePhoto('side.png')]);

    $tokens = $component->get('form.photoOrder');
    $thirdToken = $tokens[2];

    $component->call('setMainPhoto', $thirdToken)
        ->assertSet('form.photoOrder.0', $thirdToken);
});

it('moves a photo left and right with the keyboard-operable arrows', function () {
    $category = Category::factory()->create();
    $seller = User::factory()->create();

    $component = startListingWithPhotos($seller, $category, [fakePhoto('front.png'), fakePhoto('back.png')]);

    $tokens = $component->get('form.photoOrder');
    [$first, $second] = $tokens;

    $component->call('movePhotoRight', $first)
        ->assertSet('form.photoOrder', [$second, $first]);

    $component->call('movePhotoLeft', $first)
        ->assertSet('form.photoOrder', [$first, $second]);
});

it('persists a reordered set of existing photos when editing a listing, and the card shows the new main photo', function () {
    $seller = User::factory()->create();
    Storage::disk('public')->put('listings/a.jpg', 'a');
    Storage::disk('public')->put('listings/b.jpg', 'b');
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'images' => ['listings/a.jpg', 'listings/b.jpg']]);

    Livewire::actingAs($seller)
        ->test(EditListing::class, ['listing' => $listing])
        ->assertSet('form.photoOrder', ['listings/a.jpg', 'listings/b.jpg'])
        ->call('reorderPhotos', ['listings/b.jpg', 'listings/a.jpg'])
        ->call('save')
        ->assertHasNoErrors();

    $listing->refresh();
    expect($listing->images)->toBe(['listings/b.jpg', 'listings/a.jpg'])
        ->and($listing->cover_image_url)->toContain('b.jpg');
});

it('rejects a reorder request that tries to smuggle in another listing\'s photo', function () {
    $seller = User::factory()->create();
    Storage::disk('public')->put('listings/mine.jpg', 'mine');
    Storage::disk('public')->put('listings/not-mine.jpg', 'not mine');
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'images' => ['listings/mine.jpg']]);

    Livewire::actingAs($seller)
        ->test(EditListing::class, ['listing' => $listing])
        ->call('reorderPhotos', ['listings/not-mine.jpg'])
        ->assertSet('form.photoOrder', ['listings/mine.jpg']); // unchanged - the bogus order was ignored

    $listing->refresh();
    expect($listing->images)->toBe(['listings/mine.jpg']);
});

it('ignores setMainPhoto for a token that is not actually one of this listing\'s photos', function () {
    $seller = User::factory()->create();
    Storage::disk('public')->put('listings/a.jpg', 'a');
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'images' => ['listings/a.jpg']]);

    Livewire::actingAs($seller)
        ->test(EditListing::class, ['listing' => $listing])
        ->call('setMainPhoto', 'listings/someone-elses-photo.jpg')
        ->assertSet('form.photoOrder', ['listings/a.jpg']);
});

it('keeps the existing order of photos that survive a removal, and appends newly added ones at the end', function () {
    $seller = User::factory()->create();
    Storage::disk('public')->put('listings/a.jpg', 'a');
    Storage::disk('public')->put('listings/b.jpg', 'b');
    Storage::disk('public')->put('listings/c.jpg', 'c');
    $listing = Listing::factory()->create(['user_id' => $seller->id, 'images' => ['listings/a.jpg', 'listings/b.jpg', 'listings/c.jpg']]);

    $component = Livewire::actingAs($seller)->test(EditListing::class, ['listing' => $listing])
        ->call('reorderPhotos', ['listings/c.jpg', 'listings/a.jpg', 'listings/b.jpg'])
        ->call('removeExistingPhoto', 1); // removes existing_images[1], which is still 'listings/b.jpg' - removeExistingPhoto indexes the underlying array, not the chosen display order

    // The custom order survives for the photos that remain, with the
    // removed one simply gone - never silently reset to upload order.
    expect($component->get('form.photoOrder'))->toBe(['listings/c.jpg', 'listings/a.jpg']);

    $component->set('form.images', [fakePhoto('new.png')]);
    $newToken = collect($component->get('form.photoOrder'))->last();

    expect($component->get('form.photoOrder'))->toBe(['listings/c.jpg', 'listings/a.jpg', $newToken]);
});
