<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportQuickReplyResource\Pages\CreateSupportQuickReply;
use App\Filament\Resources\SupportQuickReplyResource\Pages\EditSupportQuickReply;
use App\Filament\Resources\SupportQuickReplyResource\Pages\ListSupportQuickReplies;
use App\Models\SupportQuickReply;
use App\Models\SupportContactMethod;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportQuickReplyResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SupportQuickReply::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '预设回复';
    protected static ?string $modelLabel = '预设回复';
    protected static ?string $pluralModelLabel = '预设回复';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('规则设置')->schema([
                TextInput::make('title')->label('规则名称')->required()->maxLength(255),
                Select::make('trigger_event')
                    ->label('触发时机')
                    ->options([
                        SupportQuickReply::TRIGGER_MESSAGE => '用户消息命中规则',
                        SupportQuickReply::TRIGGER_SESSION_ENTRY => '用户进入客服会话',
                    ])
                    ->default(SupportQuickReply::TRIGGER_MESSAGE)
                    ->required()
                    ->live(),
                Select::make('match_mode')
                    ->label('匹配方式')
                    ->options([
                        SupportQuickReply::MATCH_KEYWORD => '关键词',
                        SupportQuickReply::MATCH_REGEX => '正则',
                    ])
                    ->default(SupportQuickReply::MATCH_KEYWORD)
                    ->required(fn (Get $get): bool => $get('trigger_event') === SupportQuickReply::TRIGGER_MESSAGE)
                    ->visible(fn (Get $get): bool => $get('trigger_event') === SupportQuickReply::TRIGGER_MESSAGE),
                Textarea::make('match_pattern')
                    ->label('检测语句')
                    ->helperText('关键词模式可用逗号或换行分隔；正则模式支持直接写表达式。')
                    ->required(fn (Get $get): bool => $get('trigger_event') === SupportQuickReply::TRIGGER_MESSAGE)
                    ->rows(4)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('trigger_event') === SupportQuickReply::TRIGGER_MESSAGE),
                Select::make('trigger_action')
                    ->label('命中动作')
                    ->options([
                        SupportQuickReply::ACTION_REPLY => '自动回复',
                        SupportQuickReply::ACTION_AI => '接入 AI',
                        SupportQuickReply::ACTION_NOTIFY_STAFF => '提醒客服接待',
                    ])
                    ->default(SupportQuickReply::ACTION_REPLY)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('trigger_event') === SupportQuickReply::TRIGGER_MESSAGE),
                Textarea::make('body')
                    ->label('回复词 / 提示内容')
                    ->helperText('自动回复会直接发送这段内容；AI 接入和提醒客服也可用作提示文本。')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Select::make('contact_method_ids')
                    ->label('引用联系方式')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => SupportContactMethod::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (SupportContactMethod $method): array => [
                            $method->id => $method->name.(filled($method->account) ? ' / '.$method->account : ''),
                        ])
                        ->all())
                    ->helperText('被引用的联系方式会随回复显示为可点击的软件图标和名称。')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
            ])->columns(2)->columnSpanFull(),

            Section::make('内部备注')->schema([
                TextInput::make('category')->label('分组备注')->maxLength(255),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('规则名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title', 'match_pattern', 'body', 'category'], $search))
                    ->sortable(),
                TextColumn::make('trigger_event')->label('触发时机')->formatStateUsing(fn (SupportQuickReply $record): string => $record->triggerEventLabel())->badge(),
                TextColumn::make('match_mode')->label('匹配方式')->formatStateUsing(fn (string $state): string => $state === SupportQuickReply::MATCH_REGEX ? '正则' : '关键词'),
                TextColumn::make('trigger_action')->label('命中动作')->formatStateUsing(fn (string $state): string => match ($state) {
                    SupportQuickReply::ACTION_AI => '接入 AI',
                    SupportQuickReply::ACTION_NOTIFY_STAFF => '提醒客服接待',
                    default => '自动回复',
                }),
                TextColumn::make('match_pattern')->label('检测语句')->limit(36)->wrap(),
                TextColumn::make('body')->label('回复词')->limit(36)->wrap(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('is_active')->label('状态')->formatStateUsing(fn (bool $state): string => $state ? '启用' : '停用')->badge(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportQuickReplies::route('/'),
            'create' => CreateSupportQuickReply::route('/create'),
            'edit' => EditSupportQuickReply::route('/{record}/edit'),
        ];
    }
}
