<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\AdminActivityLogResource\Pages\ListAdminActivityLogs;
use App\Models\AdminActivityLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminActivityLogResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = AdminActivityLog::class;
    protected static string $permissionArea = 'activity';
    protected static ?string $navigationLabel = '操作日志';
    protected static ?string $modelLabel = '操作日志';
    protected static ?string $pluralModelLabel = '操作日志';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?int $navigationSort = 80;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('操作人')
                    ->placeholder('系统')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('操作')
                    ->state(fn (AdminActivityLog $record): string => $record->actionLabel())
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('对象')
                    ->state(fn (AdminActivityLog $record): string => $record->subjectLabel())
                    ->placeholder('-'),
                TextColumn::make('description')
                    ->label('说明')
                    ->searchable()
                    ->limit(48),
                TextColumn::make('properties')
                    ->label('操作摘要')
                    ->state(fn (AdminActivityLog $record): string => $record->propertiesSummary())
                    ->limit(96)
                    ->wrap(),
                TextColumn::make('ip_address')->label('IP')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminActivityLogs::route('/'),
        ];
    }
}
