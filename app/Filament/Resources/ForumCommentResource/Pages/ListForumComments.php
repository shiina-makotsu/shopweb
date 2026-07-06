<?php

namespace App\Filament\Resources\ForumCommentResource\Pages;

use App\Filament\Resources\ForumCommentResource;
use App\Filament\Resources\ProductCommentResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Resources\Pages\ListRecords;

class ListForumComments extends ListRecords
{
    protected static string $resource = ForumCommentResource::class;

    protected function getHeaderActions(): array
    {
        return AdminPageTabs::actions(ProductCommentResource::tabs(), 'forum');
    }
}
