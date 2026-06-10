<?php

namespace App\Filament\Resources\SupportChatSessionResource\Pages;

use App\Filament\Resources\SupportChatSessionResource;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportQuickReply;
use App\Services\SupportChatService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupportChatSession extends EditRecord
{
    protected static string $resource = SupportChatSessionResource::class;
    protected string $view = 'filament.resources.support-chat-session-resource.pages.edit-support-chat-session';

    public string $replyMessage = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->markCustomerMessagesRead();
    }

    public function quickReplies()
    {
        return SupportQuickReply::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function useQuickReply(int $replyId): void
    {
        $reply = SupportQuickReply::query()->active()->find($replyId);

        if (! $reply) {
            return;
        }

        $this->replyMessage = $reply->body;
    }

    public function sendReply(): void
    {
        $message = trim($this->replyMessage);

        if ($message === '') {
            return;
        }

        if ($this->record->isClosed()) {
            Notification::make()
                ->title('该会话窗口已关闭，不能继续回复。')
                ->danger()
                ->send();

            return;
        }

        app(SupportChatService::class)->reply($this->record, auth()->user(), $message);

        $this->replyMessage = '';
        $this->record->refresh()->load(['messages.sender', 'assignedAdmin', 'order', 'user']);

        Notification::make()
            ->title('消息已发送')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label('接待此会话')
                ->color('info')
                ->visible(fn (): bool => ! $this->record->isClosed() && ($this->record->assigned_admin_id !== auth()->id() || $this->record->status !== SupportChatSession::STATUS_ACTIVE))
                ->action(fn () => app(SupportChatService::class)->assign($this->record, auth()->user())),
            Action::make('reply')
                ->label('回复')
                ->form([
                    Textarea::make('message')->label('回复内容')->required()->rows(5),
                ])
                ->visible(fn (): bool => ! $this->record->isClosed())
                ->action(fn (array $data) => app(SupportChatService::class)->reply($this->record, auth()->user(), $data['message'])),
            Action::make('end')
                ->label('结束接待')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $this->record->isClosed() && $this->record->status !== SupportChatSession::STATUS_ENDED)
                ->action(fn () => app(SupportChatService::class)->end($this->record, auth()->user())),
        ];
    }

    public function isAdminMessage(SupportChatMessage $message): bool
    {
        return $message->sender_type === SupportChatMessage::SENDER_ADMIN;
    }

    protected function markCustomerMessagesRead(): void
    {
        $this->record->messages()
            ->whereIn('sender_type', [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->record->load(['messages.sender', 'assignedAdmin', 'order', 'user']);
    }
}
