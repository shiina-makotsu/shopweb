<?php

namespace App\Filament\Resources\SupportChatSessionResource\Pages;

use App\Filament\Resources\SupportChatSessionResource;
use App\Models\SupportChatSession;
use App\Services\SupportChatService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;

class EditSupportChatSession extends EditRecord
{
    protected static string $resource = SupportChatSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label('接待此会话')
                ->color('info')
                ->visible(fn (): bool => $this->record->assigned_admin_id !== auth()->id() || $this->record->status !== SupportChatSession::STATUS_ACTIVE)
                ->action(fn () => app(SupportChatService::class)->assign($this->record, auth()->user())),
            Action::make('reply')
                ->label('回复')
                ->form([
                    Textarea::make('message')->label('回复内容')->required()->rows(5),
                ])
                ->action(fn (array $data) => app(SupportChatService::class)->reply($this->record, auth()->user(), $data['message'])),
            Action::make('end')
                ->label('结束接待')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status !== SupportChatSession::STATUS_ENDED)
                ->action(fn () => app(SupportChatService::class)->end($this->record, auth()->user())),
        ];
    }
}
