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
        'previous_price',
        'price_dropped_at',
        'condition',
        'status',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'price_dropped_at' => 'datetime',
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
     * Listings with a genuine, currently-active price reduction. Real
     * database data only - see isHotDeal()/ListingForm::update().
     */
    public function scopeHotDeals(Builder $query): Builder
    {
        return $query->whereNotNull('previous_price')->whereColumn('previous_price', '>', 'price');
    }

    /**
     * A listing is only ever a Hot Deal because of a real, immediately-prior
     * price that was genuinely higher - never a flag anyone can set by hand.
     */
    public function isHotDeal(): bool
    {
        return $this->previous_price !== null && (float) $this->previous_price > (float) $this->price;
    }

    public function discountAmount(): ?float
    {
        return $this->isHotDeal() ? round((float) $this->previous_price - (float) $this->price, 2) : null;
    }

    public function discountPercentage(): ?int
    {
        if (! $this->isHotDeal() || (float) $this->previous_price <= 0) {
            return null;
        }

        return (int) round((((float) $this->previous_price - (float) $this->price) / (float) $this->previous_price) * 100);
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
