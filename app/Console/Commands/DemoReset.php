<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Storage;

/**
 * Resets the local database to a clean, two-student demo: migrate:fresh
 * (wipes every table, cleaner than deleting rows one by one) + DemoSeeder
 * (categories + exactly one seller, one buyer, one admin), then clears
 * uploaded listing/avatar/verification files so there's nothing left over
 * from a previous demo run. Everything else on either disk - including the
 * reputation-export signing keys - is left untouched.
 */
class DemoReset extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe the local database and reseed a clean two-student demo (local environment only)';

    /**
     * @var array<string, array<int, string>>
     */
    private const CLEARED_FOLDERS = [
        'public' => ['listings', 'avatars'],
        'local' => ['verification-documents'],
    ];

    public function handle(): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('demo:reset only runs in the local environment - refusing to continue.');

            return self::FAILURE;
        }

        // ConfirmableTrait's default callback only confirms in production -
        // this command only ever runs locally, so it must always confirm
        // (unless --force is given) rather than silently skip the prompt.
        if (! $this->confirmToProceed(
            'This wipes the ENTIRE local database (every user, listing, transaction, message, dispute...) and deletes uploaded listing photos, avatars and verification documents.',
            fn () => true
        )) {
            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->clearUploadedFiles();

        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        $this->call('audit:verify');

        $this->printAccounts();

        return self::SUCCESS;
    }

    private function clearUploadedFiles(): void
    {
        foreach (self::CLEARED_FOLDERS as $disk => $folders) {
            foreach ($folders as $folder) {
                Storage::disk($disk)->deleteDirectory($folder);
            }
        }

        $this->info('Cleared uploaded listing photos, avatars and verification documents.');
    }

    private function printAccounts(): void
    {
        $this->newLine();
        $this->info('Demo is ready. Accounts:');
        $this->table(
            ['Role', 'Name', 'Email', 'Password'],
            [
                ['Seller', 'Test Seller', 'seller@student.zut.zm', 'Seller@2026'],
                ['Buyer', 'Test Buyer', 'buyer@student.zut.zm', 'Buyer@2026'],
                ['Admin', 'UniMarket Admin', 'admin@zut.zm', 'Admin@2026'],
            ]
        );
    }
}
