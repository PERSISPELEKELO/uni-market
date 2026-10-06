<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    public const MODE_DIRECT = 'DIRECT';

    public const MODE_INSPECTION = 'INSPECTION';

    protected $fillable = [
        'name',
        'slug',
        'transaction_mode',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * A decorative emoji per category, matched by slug so it survives a
     * name tweak. Purely visual - category chips always show the name as
     * text alongside it, never the emoji alone.
     */
    public function emoji(): string
    {
        return match ($this->slug) {
            'electronics-laptops' => '💻',
            'phones-accessories' => '📱',
            'books-textbooks' => '📚',
            'clothing-fashion' => '👕',
            'shoes-footwear' => '👟',
            'dormitory-appliances' => '🏠',
            'furniture' => '🛋️',
            'bicycles-campus-transport' => '🚲',
            'food-beverages' => '🍔',
            'beauty-personal-care' => '💄',
            'sports-fitness' => '⚽',
            'stationery-school-supplies' => '✏️',
            'services' => '🛠️',
            'accommodation' => '🏘️',
            default => '🏷️',
        };
    }

    /**
     * DIRECT: buyer purchases straight to completion, no handover code or
     * inspection window (see InspectionService::completeDirectPurchase).
     */
    public function isDirect(): bool
    {
        return strtoupper((string) $this->transaction_mode) === self::MODE_DIRECT;
    }

    /**
     * INSPECTION: the existing handover-code + inspection-window escrow flow
     * applies (see HandoverVerificationService, InspectionService).
     */
    public function isInspection(): bool
    {
        return ! $this->isDirect();
    }
}
