<?php

namespace App\Filament\Resources\AiWorkflowResource\Pages;

use App\Filament\Resources\AiWorkflowResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateAiWorkflow extends CreateRecord
{
    protected static string $resource = AiWorkflowResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
