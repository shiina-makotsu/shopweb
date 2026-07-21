<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportTicketResource\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupportTicketResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SupportTicket::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '客服工单';
    protected static ?string $modelLabel = '客服工单';
    protected static ?string $pluralModelLabel = '客服工单';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SupportTicket::query()->where('status', SupportTicket::STATUS_OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('工单内容')->schema([
                Placeholder::make('user_email')->label('用户邮箱')->content(fn (?SupportTicket $record): string => $record?->user?->email ?? '-'),
                Placeholder::make('order_number')->label('关联订单')->content(fn (?SupportTicket $record): string => $record?->order?->order_number ?? '-'),
                TextInput::make('category')->label('分类')->disabled(),
                TextInput::make('subject')->label('主题')->disabled()->columnSpanFull(),
                Textarea::make('message')->label('内容')->disabled()->rows(6)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('处理记录')->schema([
                Select::make('status')->label('状态')->options([
                    SupportTicket::STATUS_OPEN => '待处理',
                    SupportTicket::STATUS_REPLIED => '已回复',
                    SupportTicket::STATUS_CLOSED => '已关闭',
                ])->required(),
                Textarea::make('admin_reply')->label('后台回复')->rows(6)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->label('主题')->searchable()->sortable(),
                TextColumn::make('category')->label('分类')->badge(),
                TextColumn::make('user.email')->label('用户')->searchable(),
                TextColumn::make('order.order_number')->label('订单号')->searchable()->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SupportTicket::STATUS_REPLIED => '已回复',
                        SupportTicket::STATUS_CLOSED => '已关闭',
                        default => '待处理',
                    })
                    ->badge(),
                TextColumn::make('created_at')->label('创建')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('markReplied')
                    ->label('标记已回复')
                    ->color('info')
                    ->visible(fn (SupportTicket $record): bool => $record->status !== SupportTicket::STATUS_CLOSED)
                    ->action(fn (SupportTicket $record) => $record->update(['status' => SupportTicket::STATUS_REPLIED])),
                Action::make('close')
                    ->label('关闭')
                    ->requiresConfirmation()
                    ->visible(fn (SupportTicket $record): bool => $record->status !== SupportTicket::STATUS_CLOSED)
                    ->action(fn (SupportTicket $record) => $record->update([
                        'status' => SupportTicket::STATUS_CLOSED,
                        'closed_at' => now(),
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
