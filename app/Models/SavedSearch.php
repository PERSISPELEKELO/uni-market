<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's standing search criteria - see the create_saved_searches_table
 * migration docblock. SavedSearches::notify-matches (routes/console.php)
 * notifies the student once per newly matching listing, never repeating a
 * listing it already reported.
 */
class SavedSearch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'term',
        'category_id',
        'condition',
        'created_at',
        'last_notified_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Active listings matching this saved search's term/category/condition,
     * created since it was last checked - reuses Listing::scopeSearch() so
     * "matches" means exactly what it means on the marketplace page itself.
     *
     * @return Builder<Listing>
     */
    public function newMatches(): Builder
    {
        return Listing::active()
            ->search($this->term)
            ->when($this->category_id, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($this->condition, fn (Builder $query, string $condition) => $query->where('condition', $condition))
            ->where('created_at', '>', $this->last_notified_at ?? $this->created_at);
    }
}
