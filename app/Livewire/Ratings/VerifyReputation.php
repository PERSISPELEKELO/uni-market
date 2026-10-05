<?php

declare(strict_types=1);

namespace App\Livewire\Ratings;

use App\Services\ReputationExporterService;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public page (no login required - see routes/web.php): anyone holding a
 * previously exported reputation file can check whether it is authentic and
 * unmodified. Verification only ever reads the server's own stored public
 * key (see ReputationExporterService) - never anything from the uploaded
 * file itself.
 */
class VerifyReputation extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?bool $isValid = null;

    public ?string $reason = null;

    public function verify(ReputationExporterService $exporter): void
    {
        $this->isValid = null;
        $this->reason = null;

        $this->validate([
            'file' => ['required', 'file', 'max:2048'],
        ], [
            'file.required' => 'Please choose a reputation export file to verify.',
        ]);

        $document = json_decode(file_get_contents($this->file->getRealPath()), true);

        if (! is_array($document)) {
            $this->isValid = false;
            $this->reason = 'That file is not valid JSON.';
            $this->file = null;

            return;
        }

        $result = $exporter->verifyExport($document);
        $this->isValid = $result['valid'];
        $this->reason = $result['reason'];
        $this->file = null;
    }

    public function render()
    {
        return view('livewire.ratings.verify-reputation')
            ->layout('layouts.app', ['title' => 'Verify Reputation Record - UniMarket']);
    }
}
