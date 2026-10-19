<?php

use App\Services\ListingImageService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = app(ListingImageService::class);
});

it('stores the original and a resized thumbnail at a derived path', function () {
    $path = $this->service->store(fakePhoto('front.png'));

    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertExists('listings/thumbs/'.basename($path));
});

it('serves the thumbnail URL once one has been generated', function () {
    $path = $this->service->store(fakePhoto('front.png'));

    expect($this->service->thumbnailUrlFor($path))->toContain('listings/thumbs/'.basename($path));
});

it('falls back to the original URL when no thumbnail exists for a path', function () {
    Storage::disk('public')->put('listings/legacy.jpg', 'old upload, before thumbnails existed');

    expect($this->service->thumbnailUrlFor('listings/legacy.jpg'))->toContain('listings/legacy.jpg')
        ->and($this->service->thumbnailUrlFor('listings/legacy.jpg'))->not->toContain('thumbs');
});

it('deletes both the original and its thumbnail together', function () {
    $path = $this->service->store(fakePhoto('front.png'));
    $thumbnailPath = 'listings/thumbs/'.basename($path);

    $this->service->delete([$path]);

    Storage::disk('public')->assertMissing($path);
    Storage::disk('public')->assertMissing($thumbnailPath);
});

it('does nothing when asked to delete an empty list', function () {
    expect(fn () => $this->service->delete([]))->not->toThrow(Throwable::class);
});
