<?php

namespace App\Filament\Resources\SupportChatSessionResource\Pages;

use App\Filament\Resources\SupportChatSessionResource;
use App\Models\SupportChatMessage;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSupportChatSessions extends ListRecords
{
    protected static string $resource = SupportChatSessionResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'unread';
    }

    public function getTabs(): array
    {
        return [
            'unread' => Tab::make('未读会话')
                ->badge(fn (): string => (string) $this->unreadQuery(static::getResource()::getEloquentQuery())->count())
                ->badgeColor('danger')
                ->query(fn (Builder $query): Builder => $this->unreadQuery($query)),
            'latest' => Tab::make('最新会话')
                ->badge(fn (): string => (string) static::getResource()::getEloquentQuery()->count()),
        ];
    }

    protected function unreadQuery(Builder $query): Builder
    {
        return $query->whereHas('messages', function (Builder $messageQuery): Builder {
            return $messageQuery
                ->whereIn('sender_type', [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST])
                ->whereNull('read_at');
        });
    }
}
