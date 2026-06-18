<?php

namespace App\Filament\Resources\ForumSectionResource\Pages;

use App\Filament\Resources\ForumSectionResource;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\EditRecord;

class EditForumSection extends EditRecord
{
    protected static string $resource = ForumSectionResource::class;

    protected function afterSave(): void
    {
        $this->record->moderators()->update(['forum_role' => 'moderator']);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
