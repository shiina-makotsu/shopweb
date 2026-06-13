<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AiUsageService;
use App\Support\AdminAccess;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserAiPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = 'AI';
    protected static string|\UnitEnum|null $navigationGroup = '用户';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static ?int $navigationSort = 35;
    protected static ?string $slug = 'user-ai';
    protected string $view = 'filament.pages.user-ai';

    /** @var array<string, mixed> */
    public array $defaults = [];

    /** @var array<string, mixed> */
    public array $userData = [];

    public ?int $selectedUserId = null;

    public string $search = '';

    public string $userFilter = 'all';

    public function mount(AiUsageService $usage): void
    {
        $settings = $usage->settings();

        $this->defaultsForm->fill($settings->only([
            'ai_default_endpoint',
            'ai_default_api_key',
            'ai_default_user_quota_k',
        ]));

        $firstUser = User::query()->where('role', 'customer')->orderBy('id')->first();
        $this->selectedUserId = $firstUser?->id;
        $this->loadUser();
    }

    public function getTitle(): string
    {
        return '用户 AI';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('customers');
    }

    public function defaultsForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('defaults')
            ->components([
                Section::make('默认总 Key')
                    ->schema([
                        TextInput::make('ai_default_endpoint')
                            ->label('默认 API URL')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('ai_default_api_key')
                            ->label('默认 API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(4096),
                        TextInput::make('ai_default_user_quota_k')
                            ->label('默认单用户 token 上限（k）')
                            ->numeric()
                            ->minValue(0)
                            ->default(100),
                    ])
                    ->columns(2),
            ]);
    }

    public function userForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('userData')
            ->components([
                Section::make('单用户配置')
                    ->schema([
                        TextInput::make('ai_quota_k')
                            ->label('用户 token 上限（k）')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('留空时使用默认单用户上限。'),
                        TextInput::make('ai_endpoint')
                            ->label('用户默认 API URL')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('ai_api_key')
                            ->label('用户默认 API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(4096),
                    ])
                    ->columns(2),
            ]);
    }

    public function saveDefaults(): void
    {
        $usage = app(AiUsageService::class);

        $usage->settings()->update($this->defaultsForm->getState());

        Notification::make()->title('默认 AI 配置已保存')->success()->send();
    }

    public function loadUser(): void
    {
        $user = $this->selectedUser();

        $this->userForm->fill([
            'ai_quota_k' => $user?->ai_quota_k,
            'ai_endpoint' => $user?->ai_endpoint,
            'ai_api_key' => $user?->ai_api_key,
        ]);
    }

    public function selectUser(int $userId): void
    {
        $user = User::query()->where('role', 'customer')->whereKey($userId)->first();

        if (! $user) {
            return;
        }

        $this->selectedUserId = $user->id;
        $this->loadUser();
    }

    public function saveUser(): void
    {
        $state = $this->userForm->getState();
        $user = $this->selectedUser();

        if (! $user) {
            Notification::make()->title('请选择用户')->danger()->send();

            return;
        }

        $user->update([
            'ai_quota_k' => blank($state['ai_quota_k'] ?? null) ? null : (int) $state['ai_quota_k'],
            'ai_endpoint' => blank($state['ai_endpoint'] ?? null) ? null : (string) $state['ai_endpoint'],
            'ai_api_key' => blank($state['ai_api_key'] ?? null) ? null : (string) $state['ai_api_key'],
        ]);

        Notification::make()->title('用户 AI 配置已保存')->success()->send();
        $this->loadUser();
    }

    public function selectedUser(): ?User
    {
        return User::query()
            ->where('role', 'customer')
            ->whereKey($this->selectedUserId)
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return $this->filteredUserQuery()
            ->withCount('aiUsageLogs')
            ->withSum('aiUsageLogs as ai_total_tokens', 'token_count')
            ->orderBy('name')
            ->limit(80)
            ->get();
    }

    private function filteredUserQuery(): Builder
    {
        $search = trim($this->search);

        return User::query()
            ->where('role', 'customer')
            ->when($this->userFilter === 'member', fn (Builder $query): Builder => $query->where('account_type', 'member'))
            ->when($this->userFilter === 'regular', fn (Builder $query): Builder => $query->where('account_type', 'regular'))
            ->when($this->userFilter === 'moderator', fn (Builder $query): Builder => $query->where('forum_role', 'moderator'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('public_id', 'like', "%{$search}%");
                });
            });
    }

    public function updatedSearch(): void
    {
        $this->selectFirstVisibleUserIfNeeded();
    }

    public function updatedUserFilter(): void
    {
        $this->selectFirstVisibleUserIfNeeded();
    }

    private function selectFirstVisibleUserIfNeeded(): void
    {
        if ($this->selectedUserId && (clone $this->filteredUserQuery())->whereKey($this->selectedUserId)->exists()) {
            return;
        }

        $this->selectedUserId = (clone $this->filteredUserQuery())->orderBy('id')->value('id');
        $this->loadUser();
    }

    /**
     * @return array<string, mixed>
     */
    public function totalStats(): array
    {
        $usage = app(AiUsageService::class);
        $totals = $usage->tokenSums();

        return [
            'total_tokens' => $usage->usedTokens(),
            'tokens_24h' => $usage->usedTokens(null, now()->subDay()),
            'prompt_tokens' => $totals['prompt_tokens'],
            'completion_tokens' => $totals['completion_tokens'],
            'model_breakdown' => $usage->modelBreakdown(),
            'hourly_usage' => $usage->hourlyModelUsage(),
            'recent_logs' => $usage->recentLogs(null, 20),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selectedUserStats(): ?array
    {
        $user = $this->selectedUser();

        if (! $user) {
            return null;
        }

        $usage = app(AiUsageService::class);
        $totals = $usage->tokenSums($user);

        return [
            'limit_k' => $usage->quotaLimitK($user),
            'remaining_k' => $usage->remainingK($user),
            'total_tokens' => $usage->usedTokens($user),
            'tokens_24h' => $usage->usedTokens($user, now()->subDay()),
            'prompt_tokens' => $totals['prompt_tokens'],
            'completion_tokens' => $totals['completion_tokens'],
            'model_breakdown' => $usage->modelBreakdown($user),
            'hourly_usage' => $usage->hourlyModelUsage($user),
            'recent_logs' => $usage->recentLogs($user, 20),
        ];
    }
}
