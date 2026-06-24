<?php

namespace App\Filament\Resources\AiWorkflowResource\Pages;

use App\Filament\Resources\AiWorkflowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiWorkflows extends ListRecords
{
    protected static string $resource = AiWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
