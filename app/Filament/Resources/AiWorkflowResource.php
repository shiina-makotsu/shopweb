<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\AiWorkflowResource\Pages\CreateAiWorkflow;
use App\Filament\Resources\AiWorkflowResource\Pages\EditAiWorkflow;
use App\Filament\Resources\AiWorkflowResource\Pages\ListAiWorkflows;
use App\Models\AiWorkflow;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AiWorkflowResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = AiWorkflow::class;
    protected static string $permissionArea = 'ai';
    protected static ?string $navigationLabel = 'AI 工作流';
    protected static ?string $modelLabel = 'AI 工作流';
    protected static ?string $pluralModelLabel = 'AI 工作流';
    protected static string|\UnitEnum|null $navigationGroup = 'AI';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('工作流信息')
                ->description('工作流可以被客服 AI、导购 AI、前台聊天或生图功能调用。')
                ->schema([
                    TextInput::make('name')
                        ->label('名称')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set): mixed => filled($state) ? $set('slug', Str::slug((string) $state) ?: Str::random(8)) : null),
                    TextInput::make('slug')
                        ->label('调用标识')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('type')
                        ->label('工作流类型')
                        ->required()
                        ->options(AiWorkflow::typeOptions())
                        ->default(AiWorkflow::TYPE_CHAT),
                    Select::make('trigger_key')
                        ->label('默认调用场景')
                        ->helperText('功能页面后续可直接按调用场景选择工作流；手动调用则只作为模板保存。')
                        ->options(AiWorkflow::triggerOptions())
                        ->searchable(),
                    TextInput::make('entry_node_id')->label('入口节点 ID')->maxLength(100),
                    TextInput::make('output_node_id')->label('输出节点 ID')->maxLength(100),
                    TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                    Toggle::make('is_active')->label('启用')->default(true),
                    Textarea::make('description')->label('说明')->rows(3)->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Hidden::make('nodes')
                ->default([])
                ->dehydrated(),
            Hidden::make('edges')
                ->default([])
                ->dehydrated(),

            Section::make('工作流画布')
                ->description('从左侧组件库拖入节点，拖动节点端口连线，也可以在画布右键添加节点。')
                ->schema([
                    View::make('filament.resources.ai-workflow-resource.workflow-canvas'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('creator'))
            ->columns([
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('slug')->label('调用标识')->searchable(),
                TextColumn::make('type')
                    ->label('类型')
                    ->formatStateUsing(fn (?string $state): string => AiWorkflow::typeOptions()[$state ?? AiWorkflow::TYPE_CHAT] ?? (string) $state),
                TextColumn::make('trigger_key')
                    ->label('调用场景')
                    ->formatStateUsing(fn (?string $state): string => $state ? (AiWorkflow::triggerOptions()[$state] ?? $state) : '-'),
                TextColumn::make('nodes_count')
                    ->label('节点')
                    ->state(fn (AiWorkflow $record): int => count($record->nodes ?? [])),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiWorkflows::route('/'),
            'create' => CreateAiWorkflow::route('/create'),
            'edit' => EditAiWorkflow::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function nodeTypeOptions(): array
    {
        return [
            'input' => '输入',
            'language_model' => '语言模型加载器',
            'chat_prompt' => '聊天提示词',
            'checkpoint_loader' => '画图模型加载器',
            'lora_loader' => 'LoRA 加载器',
            'clip_text_encode' => 'CLIP 文本编码器',
            'load_image' => '加载图片',
            'save_image' => '保存图片',
            'empty_latent_image' => '空潜空间图像',
            'vae_encode' => 'VAE 编码器',
            'vae_decode' => 'VAE 解码器',
            'k_sampler' => 'K 采样器',
            'controlnet_loader' => 'ControlNet 加载器',
            'controlnet_apply' => '应用 ControlNet',
            'upscale_model_loader' => '放大模型加载器',
            'image_upscale' => '图像放大',
            'image_scale' => '图像缩放',
            'preview_image' => '预览图片',
            'mask_load' => '加载遮罩',
            'search_model' => '搜索模型',
            'rank_model' => '排列模型',
            'reply_model' => '回复模型',
            'output' => '输出',
        ];
    }
}
