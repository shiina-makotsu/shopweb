<?php

namespace App\Filament\Resources\SupportChatSessionResource\Pages;

use App\Filament\Resources\SupportChatSessionResource;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportQuickReply;
use App\Services\ChatAttachmentService;
use App\Services\SupportChatService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\EditRecord;
use Livewire\WithFileUploads;

class EditSupportChatSession extends EditRecord
{
    use WithFileUploads;

    protected static string $resource = SupportChatSessionResource::class;
    protected string $view = 'filament.resources.support-chat-session-resource.pages.edit-support-chat-session';

    public string $replyMessage = '';

    public ?int $selectedQuickReplyId = null;

    public $replyAttachment = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->record->isClosed() && ! $this->record->isEnded()) {
            app(SupportChatService::class)->assign($this->record, auth()->user());
            $this->record->refresh();
        }

        $this->markCustomerMessagesRead();
    }

    public function quickReplies()
    {
        return SupportQuickReply::query()
            ->active()
            ->forTrigger(SupportQuickReply::TRIGGER_MESSAGE)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function useQuickReply(int $replyId): void
    {
        $reply = SupportQuickReply::query()
            ->active()
            ->forTrigger(SupportQuickReply::TRIGGER_MESSAGE)
            ->find($replyId);

        if (! $reply) {
            return;
        }

        $this->replyMessage = $reply->body;
        $this->selectedQuickReplyId = $reply->id;
    }

    public function sendReply(): void
    {
        $message = trim($this->replyMessage);

        if ($message === '' && ! $this->replyAttachment) {
            return;
        }

        if ($this->record->isClosed()) {
            Notification::make()
                ->title('该会话窗口已关闭，不能继续回复。')
                ->danger()
                ->send();

            return;
        }

        $this->validate([
            'replyAttachment' => ['nullable', 'file', 'max:20480'],
        ]);

        $attachment = $this->replyAttachment
            ? app(ChatAttachmentService::class)->storeSupportAttachment($this->replyAttachment, $this->record)
            : [];

        $quickReply = $this->selectedQuickReplyId
            ? SupportQuickReply::query()->active()->find($this->selectedQuickReplyId)
            : null;

        app(SupportChatService::class)->reply($this->record, auth()->user(), $message, $attachment, $quickReply);

        $this->replyMessage = '';
        $this->selectedQuickReplyId = null;
        $this->replyAttachment = null;
        $this->record->refresh()->load(['messages.sender', 'assignedAdmin', 'order', 'user']);

        Notification::make()
            ->title('消息已发送')
            ->success()
            ->send();
    }

    public function refreshMessages(): void
    {
        $this->record->refresh();
        $this->markCustomerMessagesRead();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...SupportChatSessionResource::approvalActions($this->record),
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
