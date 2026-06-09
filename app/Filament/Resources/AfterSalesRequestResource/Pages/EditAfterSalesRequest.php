<?php

namespace App\Filament\Resources\AfterSalesRequestResource\Pages;

use App\Filament\Resources\AfterSalesRequestResource;
use App\Models\AfterSalesRequest;
use Filament\Resources\Pages\EditRecord;

class EditAfterSalesRequest extends EditRecord
{
    protected static string $resource = AfterSalesRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === AfterSalesRequest::STATUS_RESOLVED && blank($this->record->resolved_at)) {
            $data['resolved_at'] = now();
        }

        return $data;
    }
}
