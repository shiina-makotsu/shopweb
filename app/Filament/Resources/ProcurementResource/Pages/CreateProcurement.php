<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use App\Services\ProcurementService;
use Filament\Resources\Pages\CreateRecord;

class CreateProcurement extends CreateRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ProcurementResource::normalizeFormData($data);
        $data['created_by_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(ProcurementService::class)->syncProcurement($this->record);
    }
}
