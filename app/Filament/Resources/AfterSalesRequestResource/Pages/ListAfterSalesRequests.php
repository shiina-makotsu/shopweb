<?php

namespace App\Filament\Resources\AfterSalesRequestResource\Pages;

use App\Filament\Resources\AfterSalesRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListAfterSalesRequests extends ListRecords
{
    protected static string $resource = AfterSalesRequestResource::class;

    public function callMountedAction(array $arguments = []): mixed
    {
        $statePath = $this->getMountedActionSchema()?->getStatePath();

        try {
            return parent::callMountedAction($arguments);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => [
                    str_starts_with($field, $statePath.'.') ? $field : $statePath.'.'.$field => $messages,
                ])->all());
        }
    }
}
