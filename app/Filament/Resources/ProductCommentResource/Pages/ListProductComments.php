<?php

namespace App\Filament\Resources\ProductCommentResource\Pages;

use App\Filament\Resources\ProductCommentResource;
use Filament\Resources\Pages\ListRecords;

class ListProductComments extends ListRecords
{
    protected static string $resource = ProductCommentResource::class;
}
