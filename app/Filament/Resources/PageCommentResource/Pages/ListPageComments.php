<?php

namespace App\Filament\Resources\PageCommentResource\Pages;

use App\Filament\Resources\ProductCommentResource;
use App\Filament\Resources\PageCommentResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Resources\Pages\ListRecords;

class ListPageComments extends ListRecords
{
    protected static string $resource = PageCommentResource::class;

    protected function getHeaderActions(): array
    {
        return AdminPageTabs::actions(ProductCommentResource::tabs(), 'pages');
    }
}
