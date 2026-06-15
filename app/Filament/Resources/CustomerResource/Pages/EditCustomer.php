<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        CustomerResource::recordProfileChanges($this->getRecord(), $data, auth()->user());

        return $data;
    }

    protected function getRedirectUrlParameters(): array
    {
        return [
            'record' => $this->getRecord()->getKey(),
        ];
    }
}
