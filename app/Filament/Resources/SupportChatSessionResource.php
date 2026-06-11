<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportChatSessionResource\Pages\EditSupportChatSession;
use App\Filament\Resources\SupportChatSessionResource\Pages\ListSupportChatSessions;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use App\Support\RegexSearch;
use App\Support\Url;
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
use Illuminate\Support\HtmlString;

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
        return parent::getEloquentQuery()->whereNotNull('user_id');
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
            Section::make('聊天记录')->schema([
                Placeholder::make('messages')
                    ->hiddenLabel()
                    ->content(fn (?SupportChatSession $record): HtmlString => new HtmlString(static::messagesHtml($record)))
                    ->columnSpanFull(),
            ])->columnSpanFull(),
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

    private static function messagesHtml(?SupportChatSession $record): string
    {
        if (! $record) {
            return '<p style="color:#64748b;">暂无聊天记录。</p>';
        }

        $record->loadMissing(['messages.sender']);

        if ($record->messages->isEmpty()) {
            return '<p style="color:#64748b;">暂无聊天记录。</p>';
        }

        $html = '<div style="display:flex; flex-direction:column; gap:12px;">';
        $lastAdminId = null;

        foreach ($record->messages as $message) {
            if ($message->sender_type === SupportChatMessage::SENDER_ADMIN && $lastAdminId !== $message->sender_user_id) {
                $name = e($message->sender?->displayName() ?? '后台用户');
                $html .= '<div style="display:flex; align-items:center; gap:10px; color:#64748b; font-size:12px;"><span style="height:1px; background:#cbd5e1; flex:1;"></span><span>客服 '.$name.' 为您服务</span><span style="height:1px; background:#cbd5e1; flex:1;"></span></div>';
                $lastAdminId = $message->sender_user_id;
            }

            $isAdmin = $message->sender_type === SupportChatMessage::SENDER_ADMIN;
            $name = $isAdmin ? ($message->sender?->displayName() ?? '客服') : ($record->user?->displayName() ?? $record->guest_id ?? '客户');
            $body = nl2br(e((string) $message->body));
            $attachment = '';

            if ($message->attachment_path) {
                $url = Url::route('support.messages.attachment', $message);
                $label = e($message->attachment_original_name ?: '附件');
                $attachment = '<p style="margin-top:8px;"><a href="'.e($url).'" target="_blank" rel="noopener">查看附件：'.$label.'</a></p>';
            }

            $html .= '<div style="max-width:76%; align-self:'.($isAdmin ? 'flex-start' : 'flex-end').'; border:1px solid '.($isAdmin ? '#cbd5e1' : '#bfdbfe').'; background:'.($isAdmin ? '#f8fafc' : '#eff6ff').'; padding:8px 10px; border-radius:2px;">';
            $html .= '<p style="margin:0 0 4px; color:#64748b; font-size:12px;">'.e($name).' / '.$message->created_at->format('Y-m-d H:i').'</p>';
            $html .= '<div>'.$body.'</div>'.$attachment.'</div>';
        }

        return $html.'</div>';
    }
}
