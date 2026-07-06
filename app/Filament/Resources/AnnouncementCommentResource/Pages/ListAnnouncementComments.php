<?php

namespace App\Filament\Resources\AnnouncementCommentResource\Pages;

use App\Filament\Resources\AnnouncementCommentResource;
use App\Filament\Resources\ProductCommentResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncementComments extends ListRecords
{
    protected static string $resource = AnnouncementCommentResource::class;

    protected function getHeaderActions(): array
    {
        return AdminPageTabs::actions(ProductCommentResource::tabs(), 'announcements');
    }
}
