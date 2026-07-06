<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return \App\Models\Announcement::normalizePublicationData($data, republish: true);
    }

    protected function afterSave(): void
    {
        if ($this->record->is_published) {
            $this->record->reads()->delete();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
