<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\AdminLoginLogResource\Pages\ListAdminLoginLogs;
use App\Models\AdminLoginLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdminLoginLogResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = AdminLoginLog::class;
    protected static string $permissionArea = 'activity';
    protected static ?string $navigationLabel = '登录日志';
    protected static ?string $modelLabel = '登录日志';
    protected static ?string $pluralModelLabel = '登录日志';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static ?int $navigationSort = 85;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
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
                TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('email')->label('邮箱')->searchable(),
                TextColumn::make('role')->label('角色')->badge(),
                IconColumn::make('successful')->label('成功')->boolean(),
                TextColumn::make('failure_reason')->label('失败原因')->placeholder('-'),
                TextColumn::make('ip_address')->label('IP')->toggleable(),
                TextColumn::make('user_agent')->label('User Agent')->limit(48)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminLoginLogs::route('/'),
        ];
    }
}
