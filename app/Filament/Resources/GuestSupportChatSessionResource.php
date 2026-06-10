<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuestSupportChatSessionResource\Pages\EditGuestSupportChatSession;
use App\Filament\Resources\GuestSupportChatSessionResource\Pages\ListGuestSupportChatSessions;
use App\Models\SupportChatSession;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class GuestSupportChatSessionResource extends SupportChatSessionResource
{
    protected static ?string $navigationLabel = '游客会话';
    protected static ?string $modelLabel = '游客会话';
    protected static ?string $pluralModelLabel = '游客会话';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;
    protected static ?int $navigationSort = 11;

    public static function getEloquentQuery(): Builder
    {
        return SupportChatSession::query()
            ->whereNull('user_id')
            ->whereNotNull('guest_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuestSupportChatSessions::route('/'),
            'edit' => EditGuestSupportChatSession::route('/{record}/edit'),
        ];
    }
}
