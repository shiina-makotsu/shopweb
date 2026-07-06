<?php

namespace App\Filament\Resources\AnnouncementCommentResource\Pages;

use App\Filament\Resources\AnnouncementCommentResource;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncementComments extends ListRecords
{
    protected static string $resource = AnnouncementCommentResource::class;
}
