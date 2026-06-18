<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use App\Services\ProcurementService;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\EditRecord;

class EditProcurement extends EditRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ProcurementResource::normalizeFormData($data);
    }

    protected function afterSave(): void
    {
        app(ProcurementService::class)->syncProcurement($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
