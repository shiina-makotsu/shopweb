<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportChatSessionResource\Pages\EditSupportChatSession;
use App\Filament\Resources\SupportChatSessionResource\Pages\ListSupportChatSessions;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportChatSessionResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SupportChatSession::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '客服会话';
    protected static ?string $modelLabel = '客服会话';
    protected static ?string $pluralModelLabel = '客服会话';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('user_id')
            ->whereHas('messages');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingReceptionQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('会话信息')->schema([
                Placeholder::make('customer')->label('客户')->content(fn (?SupportChatSession $record): string => $record?->user?->displayName() ?? $record?->guest_id ?? '-'),
                Placeholder::make('assigned_admin')->label('当前客服')->content(fn (?SupportChatSession $record): string => $record?->assignedAdmin?->displayName() ?? '尚未接入'),
                Placeholder::make('order_number')->label('关联订单')->content(fn (?SupportChatSession $record): string => $record?->order?->order_number ?? '-'),
                Placeholder::make('status_label')->label('状态')->content(fn (?SupportChatSession $record): string => static::statusLabel($record?->status)),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('会话')->formatStateUsing(fn ($state): string => '#'.$state)->sortable(),
                TextColumn::make('user.name')
                    ->label('客户')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $userQuery) => RegexSearch::where($userQuery, ['name', 'email', 'public_id'], $search))),
                TextColumn::make('guest_id')->label('游客')->searchable()->toggleable(),
                TextColumn::make('order.order_number')->label('订单号')->searchable()->toggleable(),
                TextColumn::make('assignedAdmin.name')->label('客服')->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->badge(),
                TextColumn::make('last_message_at')->label('最后消息')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('served_count')->label('完成接待')->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('assign')
                    ->label('接待')
                    ->color('info')
                    ->visible(fn (SupportChatSession $record): bool => ! $record->isClosed() && ($record->assigned_admin_id !== auth()->id() || $record->status !== SupportChatSession::STATUS_ACTIVE))
                    ->action(fn (SupportChatSession $record) => app(SupportChatService::class)->assign($record, auth()->user())),
                Action::make('reply')
                    ->label('回复')
                    ->form([
                        Textarea::make('message')->label('回复内容')->required()->rows(4),
                    ])
                    ->visible(fn (SupportChatSession $record): bool => ! $record->isClosed())
                    ->action(fn (SupportChatSession $record, array $data) => app(SupportChatService::class)->reply($record, auth()->user(), $data['message'])),
                Action::make('end')
                    ->label('结束接待')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SupportChatSession $record): bool => ! $record->isClosed() && $record->status !== SupportChatSession::STATUS_ENDED)
                    ->action(fn (SupportChatSession $record) => app(SupportChatService::class)->end($record, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportChatSessions::route('/'),
            'edit' => EditSupportChatSession::route('/{record}/edit'),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            SupportChatSession::STATUS_ACTIVE => '接待中',
            SupportChatSession::STATUS_ENDED => '已结束',
            SupportChatSession::STATUS_CLOSED => '用户已关闭',
            default => '等待接入',
        };
    }

    protected static function pendingReceptionQuery(): Builder
    {
        return SupportChatSession::query()
            ->whereNotNull('user_id')
            ->where('status', SupportChatSession::STATUS_OPEN)
            ->whereNull('assigned_admin_id')
            ->whereHas('messages', fn (Builder $query): Builder => $query->whereIn('sender_type', ['customer', 'guest']));
    }
}
