<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Renames the 5 originally-seeded categories to the canonical English
     * names/slugs (in place, preserving every listing's category_id), then
     * guarantees the full canonical 15-category list exists regardless -
     * on a fresh database the 5 old-slug rows never existed to rename in
     * the first place (DatabaseSeeder only runs when explicitly invoked,
     * never automatically by `migrate`), so insertOrIgnore below is what
     * actually creates them there. Finally removes any category outside
     * this canonical list - specifically the random Latin placeholder names
     * (e.g. "Explicabo Odit") that CategoryFactory generates, which only
     * ever entered this database because Listing::factory()/
     * Transaction::factory() were run directly against it, not because of
     * anything in DatabaseSeeder.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $renames = [
                'textbooks-study' => 'books-textbooks',
                'dorm-gear-appliances' => 'dormitory-appliances',
                'apparel-fashion' => 'clothing-fashion',
                'bikes-campus-transport' => 'bicycles-campus-transport',
            ];

            foreach ($renames as $oldSlug => $newSlug) {
                DB::table('categories')->where('slug', $oldSlug)->update(['slug' => $newSlug, 'updated_at' => now()]);
            }

            $canonical = [
                ['name' => 'Electronics & Laptops', 'slug' => 'electronics-laptops', 'transaction_mode' => 'INSPECTION'],
                ['name' => 'Phones & Accessories', 'slug' => 'phones-accessories', 'transaction_mode' => 'INSPECTION'],
                ['name' => 'Books & Textbooks', 'slug' => 'books-textbooks', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Clothing & Fashion', 'slug' => 'clothing-fashion', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Shoes & Footwear', 'slug' => 'shoes-footwear', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Dormitory & Appliances', 'slug' => 'dormitory-appliances', 'transaction_mode' => 'INSPECTION'],
                ['name' => 'Furniture', 'slug' => 'furniture', 'transaction_mode' => 'INSPECTION'],
                ['name' => 'Bicycles & Campus Transport', 'slug' => 'bicycles-campus-transport', 'transaction_mode' => 'INSPECTION'],
                ['name' => 'Food & Beverages', 'slug' => 'food-beverages', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Stationery & School Supplies', 'slug' => 'stationery-school-supplies', 'transaction_mode' => 'DIRECT'],
                ['name' => 'Services', 'slug' => 'services', 'transaction_mode' => 'DIRECT'],
                // No accommodation-specific booking system exists yet; kept safe (INSPECTION)
                // but freely admin-editable like every other category, per the spec's own
                // "Configurable" note - there is no third transaction_mode value.
                ['name' => 'Accommodation', 'slug' => 'accommodation', 'transaction_mode' => 'INSPECTION'],
                ['name' => 'Other', 'slug' => 'other', 'transaction_mode' => 'INSPECTION'],
            ];

            foreach ($canonical as $category) {
                DB::table('categories')->updateOrInsert(
                    ['slug' => $category['slug']],
                    $category + ['created_at' => now(), 'updated_at' => now()]
                );
            }

            $canonicalSlugs = array_column($canonical, 'slug');
            $garbageCategoryIds = DB::table('categories')->whereNotIn('slug', $canonicalSlugs)->pluck('id');

            if ($garbageCategoryIds->isNotEmpty()) {
                // Hard-delete (not soft-delete) any listing left over from a factory
                // call against this database - it was never real marketplace data.
                DB::table('listings')->whereIn('category_id', $garbageCategoryIds)->delete();
                DB::table('categories')->whereIn('id', $garbageCategoryIds)->delete();
            }
        });
    }

    /**
     * Not reversible: the pre-fix state was corrupt placeholder data, not a
     * legitimate configuration worth restoring.
     */
    public function down(): void
    {
        //
    }
};
