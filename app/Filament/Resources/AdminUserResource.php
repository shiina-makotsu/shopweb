<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminUserResource\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUserResource\Pages\EditAdminUser;
use App\Filament\Resources\AdminUserResource\Pages\ListAdminUsers;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationLabel = '后台用户';
    protected static ?string $modelLabel = '后台用户';
    protected static ?string $pluralModelLabel = '后台用户';
    protected static string|\UnitEnum|null $navigationGroup = '用户';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static ?int $navigationSort = 60;
    protected static ?string $recordRouteKeyName = 'id';

    public static function canAccess(): bool
    {
        return AdminAccess::can('admin-users');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', AdminAccess::panelRoles());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('用户名')->required()->maxLength(255),
            TextInput::make('public_id')
                ->label('用户 ID')
                ->required()
                ->regex('/^[A-Za-z0-9_]+$/')
                ->unique(ignoreRecord: true)
                ->maxLength(40),
            TextInput::make('email')->label('邮箱')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            FileUpload::make('avatar_path')
                ->label('头像')
                ->disk('public_uploads')
                ->directory('avatars')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                ->maxSize(5120)
                ->openable()
                ->downloadable(),
            Textarea::make('profile_intro')
                ->label('个人简介')
                ->rows(4)
                ->maxLength(1000)
                ->columnSpanFull(),
            Hidden::make('account_type')->default('regular'),
            Select::make('role')
                ->label('后台角色')
                ->required()
                ->options(array_intersect_key(AdminAccess::roles(), array_flip(AdminAccess::panelRoles())))
                ->default(AdminAccess::ROLE_ADMIN),
            TextInput::make('password')
                ->label('密码')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(8)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('头像')
                    ->disk('public_uploads')
                    ->imageSize(40),
                TextColumn::make('name')
                    ->label('用户名')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name'], $search))
                    ->sortable(),
                TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['email'], $search))
                    ->sortable(),
                TextColumn::make('public_id')
                    ->label('用户 ID')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['public_id'], $search))
                    ->sortable(),
                TextColumn::make('role')
                    ->label('后台角色')
                    ->formatStateUsing(fn (string $state): string => AdminAccess::roles()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->id() !== $record->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
