<?php

namespace App\Filament\Resources\SupportChatSessionResource\Pages;

use App\Filament\Resources\SupportChatSessionResource;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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
            'active' => Tab::make('接待中会话')
                ->badge(fn (): string => (string) $this->activeQuery(static::getResource()::getEloquentQuery())->count())
                ->query(fn (Builder $query): Builder => $this->activeQuery($query)),
            'ended' => Tab::make('已结束会话')
                ->badge(fn (): string => (string) $this->endedQuery(static::getResource()::getEloquentQuery())->count())
                ->query(fn (Builder $query): Builder => $this->endedQuery($query)),
        ];
    }

    protected function unreadQuery(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [SupportChatSession::STATUS_ENDED, SupportChatSession::STATUS_CLOSED])
            ->whereHas('messages', fn (Builder $messageQuery): Builder => $messageQuery
                ->where('sender_type', $this->customerSenderType())
                ->whereNull('read_at'));
    }

    protected function activeQuery(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [SupportChatSession::STATUS_ENDED, SupportChatSession::STATUS_CLOSED])
            ->whereHas('messages', fn (Builder $messageQuery): Builder => $messageQuery
                ->where('sender_type', SupportChatMessage::SENDER_ADMIN))
            ->whereDoesntHave('messages', fn (Builder $messageQuery): Builder => $messageQuery
                ->where('sender_type', $this->customerSenderType())
                ->whereNull('read_at'));
    }

    protected function endedQuery(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SupportChatSession::STATUS_ENDED,
            SupportChatSession::STATUS_CLOSED,
        ]);
    }

    protected function customerSenderType(): string
    {
        return static::getResource() === \App\Filament\Resources\GuestSupportChatSessionResource::class
            ? SupportChatMessage::SENDER_GUEST
            : SupportChatMessage::SENDER_CUSTOMER;
    }
}
