<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precomputed content-based "Similar items" result - see the
 * create_listing_similarities_table migration docblock and
 * ContentSimilarityService.
 */
class ListingSimilarity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'listing_id',
        'similar_listing_id',
        'score',
        'computed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'computed_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function similarListing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'similar_listing_id');
    }
}
