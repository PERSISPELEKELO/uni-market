<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Categories only - no test suite sets $seed/$seeder to auto-run this,
     * so nothing in `php artisan test` depends on it, and no real demo data
     * (users, listings, transactions, disputes) is created here any more.
     * For a demo-ready database with test accounts, use `php artisan
     * demo:reset` (DemoSeeder) instead; for a large realistic BI dataset,
     * use `php artisan db:seed --class=InsightsDemoSeeder`.
     */
    public function run(): void
    {
        $this->seedCategories();
    }

    /**
     * The full canonical category list (see the 2026_09_28_153539 migration,
     * which fixes this same list in any database that already has the old
     * 5-category set or leftover CategoryFactory placeholder names).
     * updateOrCreate keeps this idempotent and self-correcting on reseed.
     */
    public function seedCategories(): void
    {
        $categories = [
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
            ['name' => 'Accommodation', 'slug' => 'accommodation', 'transaction_mode' => 'INSPECTION'],
            ['name' => 'Other', 'slug' => 'other', 'transaction_mode' => 'INSPECTION'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
