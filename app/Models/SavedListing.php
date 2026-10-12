<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Toggle-state behind the bookmark icon and the Buying page's "Saved" list
 * - see the create_saved_listings_table migration docblock.
 */
class SavedListing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'listing_id',
        'price_at_save',
        'created_at',
    ];

    protected $casts = [
        'price_at_save' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * True once the listing's current price is genuinely below what it was
     * when the student saved it - the "price dropped" alert on the Buying
     * page.
     */
    public function hasPriceDrop(): bool
    {
        return (float) $this->listing->price < (float) $this->price_at_save;
    }
}
