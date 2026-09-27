<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppealResource;
use App\Filament\Resources\DisputeResource;
use App\Filament\Resources\StudentVerificationDocumentResource;
use App\Models\Appeal;
use App\Models\Dispute;
use App\Models\StudentVerificationDocument;
use Filament\Widgets\Widget;

/**
 * Everything an admin needs to act on today, in one place, so they don't
 * have to go hunting through every resource to find it. Every count is a
 * real query - nothing here is invented.
 */
class PendingActions extends Widget
{
    protected static string $view = 'filament.widgets.pending-actions';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array{label: string, count: int, url: string, urgent: bool}>
     */
    public function getItems(): array
    {
        $openDisputes = Dispute::whereIn('status', ['open', 'under_review'])->count();
        $pendingVerifications = StudentVerificationDocument::where('status', StudentVerificationDocument::STATUS_PENDING)->count();
        $pendingAppeals = Appeal::whereIn('status', [Appeal::STATUS_PENDING, Appeal::STATUS_UNDER_REVIEW])->count();

        return array_values(array_filter([
            $openDisputes > 0 ? [
                'label' => $openDisputes === 1 ? 'dispute requires attention' : 'disputes require attention',
                'count' => $openDisputes,
                'url' => DisputeResource::getUrl('index'),
                'urgent' => true,
            ] : null,

            $pendingVerifications > 0 ? [
                'label' => $pendingVerifications === 1 ? 'student verification awaiting review' : 'student verifications awaiting review',
                'count' => $pendingVerifications,
                'url' => StudentVerificationDocumentResource::getUrl('index'),
                'urgent' => false,
            ] : null,

            $pendingAppeals > 0 ? [
                'label' => $pendingAppeals === 1 ? 'appeal awaiting review' : 'appeals awaiting review',
                'count' => $pendingAppeals,
                'url' => AppealResource::getUrl('index'),
                'urgent' => false,
            ] : null,
        ]));
    }
}
