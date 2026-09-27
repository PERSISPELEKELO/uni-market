<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->action(function (Actions\DeleteAction $action): void {
                    if ($this->record->listings()->exists()) {
                        Notification::make()
                            ->title('Cannot delete this category')
                            ->body('It still has listings. Move or remove them first.')
                            ->danger()
                            ->send();

                        $action->cancel();

                        return;
                    }

                    $this->record->delete();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
