<?php

namespace App\Filament\Resources\ForumSectionResource\Pages;

use App\Filament\Resources\ForumSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListForumSections extends ListRecords
{
    protected static string $resource = ForumSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('创建分区'),
        ];
    }
}
