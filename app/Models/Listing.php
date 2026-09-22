<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SOLD = 'sold';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Item conditions accepted by the marketplace, keyed by stored value.
     *
     * @var array<string, string>
     */
    public const CONDITIONS = [
        'new' => 'Brand new',
        'like_new' => 'Like new',
        'good' => 'Good',
        'fair' => 'Fair',
    ];

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'price',
        'condition',
        'status',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function activeReservations(): HasMany
    {
        return $this->reservations()->active()->with('buyer')->latest();
    }

    public function hasActiveReservationFrom(?User $user): bool
    {
        return $user !== null && $this->reservations()->active()->where('buyer_id', $user->id)->exists();
    }

    /**
     * Listings that are currently open for purchase.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Match a search term against the title and description, escaping LIKE wildcards.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $escaped = addcslashes($term, '\\%_');

        return $query->where(function (Builder $inner) use ($escaped): void {
            $inner->where('title', 'like', "%{$escaped}%")
                ->orWhere('description', 'like', "%{$escaped}%");
        });
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $user->id === $this->user_id;
    }

    /**
     * Public URLs of every stored photo.
     *
     * @return Attribute<list<string>, never>
     */
    protected function imageUrls(): Attribute
    {
        return Attribute::get(fn (): array => collect($this->images ?? [])
            ->map(fn (string $path): string => asset('storage/'.$path))
            ->values()
            ->all());
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_urls[0] ?? null);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function conditionLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::CONDITIONS[$this->condition] ?? ucfirst(str_replace('_', ' ', (string) $this->condition)));
    }
}
