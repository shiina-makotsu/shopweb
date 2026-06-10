<?php

namespace App\Filament\Resources\GuestSupportChatSessionResource\Pages;

use App\Filament\Resources\GuestSupportChatSessionResource;
use App\Filament\Resources\SupportChatSessionResource\Pages\ListSupportChatSessions;

class ListGuestSupportChatSessions extends ListSupportChatSessions
{
    protected static string $resource = GuestSupportChatSessionResource::class;
}
