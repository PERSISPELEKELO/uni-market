<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\Reservation;
use App\Models\SearchLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only realistic demo data for Business Intelligence development.
 * Everything here is obviously synthetic (fake()-generated names/emails) -
 * never real student data, and this seeder refuses to run outside `local`
 * even if called directly. Not registered in DatabaseSeeder::run() by
 * default - run explicitly with `php artisan db:seed --class=InsightsDemoSeeder`.
 */
class InsightsDemoSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ITEM_NAMES = [
        'electronics-laptops' => ['HP Pavilion 15 Laptop', 'Dell Inspiron 14', 'Lenovo ThinkPad E14', 'MacBook Air M1', 'Acer Aspire 5', 'Samsung 24" Monitor', 'LG 22" Monitor', 'Logitech Wireless Mouse', 'Bluetooth Speaker', 'Scientific Calculator fx-991', 'Portable SSD 1TB', 'Laptop Cooling Pad', 'HP Wireless Keyboard', 'External DVD Drive'],
        'phones-accessories' => ['iPhone 11 64GB', 'iPhone 8 Plus', 'Samsung Galaxy A54', 'Samsung Galaxy S21', 'Tecno Camon 19', 'Infinix Note 12', 'Itel Vision 3', 'Phone Screen Protector Pack', 'USB-C Fast Charger', 'Lightning Cable - 1m', 'Power Bank 20000mAh', 'Wireless Earbuds', 'Phone Tripod Stand', 'Phone Case Bundle'],
        'books-textbooks' => ['Organic Chemistry Textbook', 'Introduction to Algorithms', 'Engineering Mechanics Statics', 'Principles of Economics', 'Human Anatomy Atlas', 'Database Systems Concepts', 'Fundamentals of Nursing', 'Calculus Early Transcendentals', 'Business Law Casebook', 'Microbiology Lecture Notes', 'Financial Accounting Textbook', 'Research Methods Handbook'],
        'clothing-fashion' => ['Campus Hoodie - Navy', 'Formal Blazer Size M', 'Denim Jacket', 'Ankara Print Dress', 'Graduation Gown', 'Winter Jacket', 'Chitenge Skirt Set', 'Men\'s Chinos - Size 32', 'Ladies Office Wear Set'],
        'shoes-footwear' => ['Nike Running Shoes Size 9', 'Campus Sneakers', 'Leather Office Shoes', 'Sandals - Size 8', 'Soccer Boots Size 10', 'Ladies Heels - Size 7', 'Hiking Boots'],
        'dormitory-appliances' => ['Mini Fridge 45L', 'Electric Kettle', 'Table Fan', 'Study Desk Lamp', 'Induction Cooker', 'Iron Box', 'Room Heater', 'Microwave - Small', 'Electric Blanket'],
        'furniture' => ['Study Desk', 'Plastic Wardrobe', 'Bunk Bed Frame', 'Bookshelf - 3 Tier', 'Office Chair', 'Foam Mattress - Single', 'Bedside Table', 'Floor Mat'],
        'bicycles-campus-transport' => ['Mountain Bike 21-Speed', 'Campus Commuter Bicycle', 'Bicycle Helmet', 'Bike Lock', 'Scooter - Electric', 'Bicycle Repair Kit'],
        'food-beverages' => ['Homemade Chicken Pie (6 pack)', 'Fresh Fruit Juice 2L', 'Snack Combo Pack', 'Instant Noodles Bulk Pack', 'Fritters & Scones Tray', 'Packed Lunch - Weekly Plan', 'Mixed Nuts & Dried Fruit'],
        'beauty-personal-care' => ['Shea Butter Lotion', 'Hair Braiding Kit', 'Skincare Bundle', 'Perfume - 50ml', 'Natural Hair Oil Set', 'Makeup Brush Set'],
        'sports-fitness' => ['Resistance Bands Set', 'Football - Size 5', 'Yoga Mat', 'Dumbbells 2x5kg', 'Skipping Rope', 'Netball - Size 5'],
        'stationery-school-supplies' => ['Scientific Notebook Set', 'Mathematical Drawing Set', 'A4 Printing Paper Ream', 'Highlighter Pack', 'Lever Arch Files (5)', 'Sticky Notes Bundle'],
        'services' => ['Assignment Typing Service', 'Laptop Repair Service', 'Hair Braiding Appointment', 'Graphic Design Service', 'Tutoring - Mathematics', 'Photography Session'],
        'accommodation' => ['Shared Room Near Campus', 'Self-Contained Bedsitter', 'Hostel Bed Space - Semester', 'Studio Apartment - Short Let'],
        'other' => ['Scientific Calculator', 'USB Flash Drive 32GB', 'Extension Cable', 'Umbrella', 'Padlock Set', 'Travel Bag - Medium'],
    ];

    /**
     * @var array<string, array{0: float, 1: float}>
     */
    private const PRICE_RANGES = [
        'electronics-laptops' => [800, 9000],
        'phones-accessories' => [100, 6000],
        'books-textbooks' => [80, 600],
        'clothing-fashion' => [80, 500],
        'shoes-footwear' => [150, 700],
        'dormitory-appliances' => [200, 1800],
        'furniture' => [250, 2200],
        'bicycles-campus-transport' => [600, 2500],
        'food-beverages' => [30, 150],
        'beauty-personal-care' => [50, 400],
        'sports-fitness' => [60, 500],
        'stationery-school-supplies' => [30, 250],
        'services' => [50, 600],
        'accommodation' => [800, 3500],
        'other' => [30, 300],
    ];

    /**
     * Search terms with no real matching listing - the "unmet demand" signal.
     *
     * @var array<int, string>
     */
    private const ZERO_RESULT_TERMS = ['ps5 console', 'gaming chair', 'air conditioner', 'projector', 'coffee maker', 'guitar'];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('InsightsDemoSeeder only runs in the local environment. Skipped.');

            return;
        }

        $schools = array_keys(config('zut.schools'));
        $categories = Category::all()->keyBy('slug');

        $students = $this->createStudents($schools);
        $listings = $this->createListings($categories, $students);
        $this->createTransactionsAndReservations($listings, $students);
        $this->createListingViews($listings, $students);
        $this->createSearchLogs($students);

        $this->command?->info('Insights demo data created: '.$students->count().' students, '.$listings->count().' listings.');
    }

    /**
     * @param  array<int, string>  $schools
     * @return Collection<int, User>
     */
    private function createStudents(array $schools): Collection
    {
        return collect(range(1, 60))->map(function () use ($schools) {
            return User::create([
                'name' => fake()->unique()->name(),
                'email' => fake()->unique()->safeEmail(),
                'student_id' => (string) fake()->unique()->numberBetween(2020100000, 2026999999),
                'phone_number' => null,
                'password' => Hash::make('password123'),
                'role' => 'student',
                'is_verified' => fake()->boolean(70),
                'year_of_study' => fake()->numberBetween(1, 4),
                'school' => fake()->randomElement($schools),
                'email_verified_at' => now(),
            ]);
        });
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @param  Collection<int, User>  $students
     * @return Collection<int, Listing>
     */
    private function createListings($categories, $students): Collection
    {
        $catalogue = collect();

        foreach (self::ITEM_NAMES as $slug => $names) {
            if ($categories->has($slug)) {
                foreach ($names as $name) {
                    $catalogue->push([$slug, $name]);
                }
            }
        }

        // Reach roughly 150 listings by letting some of the catalogue's more
        // popular entries be sold by more than one student - exactly how a
        // real campus marketplace ends up with several near-identical
        // textbooks or calculators listed by different sellers, not by
        // inventing more unique item names than genuinely exist.
        $target = 150;
        $topUp = max(0, $target - $catalogue->count());
        $entries = $catalogue->concat($catalogue->random(min($topUp, $catalogue->count())));

        return $entries->map(function (array $entry) use ($categories, $students): Listing {
            [$slug, $name] = $entry;
            $category = $categories->get($slug);
            [$min, $max] = self::PRICE_RANGES[$slug];
            $seller = $students->random();

            return Listing::create([
                'user_id' => $seller->id,
                'category_id' => $category->id,
                'title' => $name,
                'description' => "Genuine {$name}, in good condition. Message me for more photos or to arrange a campus meet-up.",
                'price' => fake()->randomFloat(2, $min, $max),
                'condition' => fake()->randomElement(array_keys(Listing::CONDITIONS)),
                'status' => Listing::STATUS_ACTIVE,
                'images' => [],
                'created_at' => now()->subDays(fake()->numberBetween(0, 180)),
            ]);
        })->values();
    }

    /**
     * Completed sales spread over the last 6 months, weighted toward two
     * "semester start" peak weeks - plus a handful of plain active
     * reservations with no transaction yet, to represent current interest.
     */
    private function createTransactionsAndReservations($listings, $students): void
    {
        $peakAnchors = [now()->subMonths(5), now()->subMonths(2)];

        foreach ($listings->shuffle()->take((int) ($listings->count() * 0.4)) as $listing) {
            $buyer = $students->reject(fn (User $u) => $u->id === $listing->user_id)->random();

            $completedAt = fake()->boolean(60)
                ? fake()->randomElement($peakAnchors)->copy()->addDays(fake()->numberBetween(-10, 10))
                : now()->subDays(fake()->numberBetween(0, 180));

            Transaction::create([
                'listing_id' => $listing->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $listing->user_id,
                'amount' => $listing->price,
                'status' => 'COMPLETED',
                'transaction_mode' => $listing->category->transaction_mode,
                'completed_at' => $completedAt,
                'created_at' => $completedAt->copy()->subDays(fake()->numberBetween(1, 5)),
            ]);

            $listing->update(['status' => Listing::STATUS_SOLD]);
        }

        foreach ($listings->shuffle()->take((int) ($listings->count() * 0.15)) as $listing) {
            if ($listing->status !== Listing::STATUS_ACTIVE) {
                continue;
            }

            $buyer = $students->reject(fn (User $u) => $u->id === $listing->user_id)->random();

            Reservation::create([
                'listing_id' => $listing->id,
                'buyer_id' => $buyer->id,
                'status' => Reservation::STATUS_ACTIVE,
                'created_at' => now()->subDays(fake()->numberBetween(0, 14)),
            ]);
        }
    }

    /**
     * A skewed distribution so a handful of listings are genuinely
     * "trending" rather than every listing getting a similar view count.
     */
    private function createListingViews($listings, $students): void
    {
        $rows = [];

        foreach ($listings as $listing) {
            $viewCount = fake()->boolean(20) ? fake()->numberBetween(30, 80) : fake()->numberBetween(0, 15);

            for ($i = 0; $i < $viewCount; $i++) {
                $viewer = fake()->boolean(40) ? $students->reject(fn (User $u) => $u->id === $listing->user_id)->random() : null;

                $rows[] = [
                    'listing_id' => $listing->id,
                    'viewer_id' => $viewer?->id,
                    'session_hash' => hash('sha256', (string) fake()->uuid()),
                    'viewed_at' => now()->subDays(fake()->numberBetween(0, 30))->subMinutes(fake()->numberBetween(0, 1440)),
                ];
            }
        }

        collect($rows)->chunk(500)->each(fn ($chunk) => ListingView::insert($chunk->all()));
    }

    private function createSearchLogs($students): void
    {
        $realTerms = collect(self::ITEM_NAMES)->flatten()->map(fn (string $name) => strtolower(explode(' ', $name)[0]))->unique()->values();

        $rows = [];

        foreach (range(1, 160) as $_) {
            $term = $realTerms->random();

            $rows[] = [
                'term' => $term,
                'results_count' => Listing::query()->where('title', 'like', "%{$term}%")->count(),
                'user_id' => fake()->boolean(50) ? $students->random()->id : null,
                'created_at' => now()->subDays(fake()->numberBetween(0, 60)),
            ];
        }

        foreach (self::ZERO_RESULT_TERMS as $term) {
            foreach (range(1, fake()->numberBetween(3, 9)) as $_) {
                $rows[] = [
                    'term' => $term,
                    'results_count' => 0,
                    'user_id' => fake()->boolean(50) ? $students->random()->id : null,
                    'created_at' => now()->subDays(fake()->numberBetween(0, 60)),
                ];
            }
        }

        SearchLog::insert($rows);
    }
}
