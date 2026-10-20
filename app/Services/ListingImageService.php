<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Generates a small resized thumbnail alongside every listing photo upload
 * - x-listing-card (the marketplace grid, carousels, search results) loads
 * this instead of the full original, while the listing page's own
 * gallery/lightbox keeps using the original, unresized photo untouched.
 *
 * Thumbnails live at a derived path (same filename, under listings/thumbs)
 * rather than a second database column, so nothing about the existing
 * `images` array column or ListingForm's validation/diffing changes.
 */
class ListingImageService
{
    private const THUMBNAIL_WIDTH = 480;

    private const THUMBNAIL_DIRECTORY = 'listings/thumbs';

    private const DISK = 'public';

    /**
     * Stores the original upload, then generates its thumbnail. Returns
     * the original's path - exactly what ListingForm stored before this,
     * so callers don't need to know a thumbnail exists at all. Accepts the
     * base Illuminate\Http\UploadedFile rather than Livewire's own
     * TemporaryUploadedFile subclass, so tests can pass a plain
     * UploadedFile::fake() upload too.
     */
    public function store(UploadedFile $image): string
    {
        $path = $image->store('listings', self::DISK);

        $this->generateThumbnail($path);

        return $path;
    }

    /**
     * Deletes a set of original images and their derived thumbnails
     * together - callers only ever pass the original paths they already
     * track, same as before this service existed.
     *
     * @param  array<int, string>  $paths
     */
    public function delete(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        Storage::disk(self::DISK)->delete($paths);
        Storage::disk(self::DISK)->delete(array_map($this->thumbnailPath(...), $paths));
    }

    /**
     * Where a listing's thumbnail lives for a given original path - falls
     * back to the original itself if no thumbnail was ever generated for
     * it (e.g. a photo uploaded before this feature existed), so a card
     * never shows a broken image while the catalogue migrates over.
     */
    public function thumbnailUrlFor(string $originalPath): string
    {
        $thumbnailPath = $this->thumbnailPath($originalPath);

        return Storage::disk(self::DISK)->exists($thumbnailPath)
            ? asset('storage/'.$thumbnailPath)
            : asset('storage/'.$originalPath);
    }

    private function thumbnailPath(string $originalPath): string
    {
        return self::THUMBNAIL_DIRECTORY.'/'.basename($originalPath);
    }

    private function generateThumbnail(string $originalPath): void
    {
        $disk = Storage::disk(self::DISK);
        $thumbnailPath = $this->thumbnailPath($originalPath);

        if (! $disk->exists(dirname($thumbnailPath))) {
            $disk->makeDirectory(dirname($thumbnailPath));
        }

        try {
            ImageManager::gd()
                ->read($disk->path($originalPath))
                ->scaleDown(width: self::THUMBNAIL_WIDTH)
                ->save($disk->path($thumbnailPath));
        } catch (\Throwable $exception) {
            // A thumbnail is a nice-to-have, never a reason to fail the
            // whole listing save - thumbnailUrlFor() already falls back to
            // the original when no thumbnail exists.
            report($exception);
        }
    }
}
