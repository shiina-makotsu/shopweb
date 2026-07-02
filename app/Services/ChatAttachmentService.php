<?php

namespace App\Services;

use App\Models\SupportChatSession;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ChatAttachmentService
{
    /**
     * @return array<string, mixed>
     */
    public function storeSupportAttachment(UploadedFile|TemporaryUploadedFile $file, SupportChatSession|int $session): array
    {
        $sessionId = $session instanceof SupportChatSession ? $session->id : $session;
        $path = $file->store('session-'.$sessionId, 'support_attachments');

        return $this->payload($file, $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function storePrivateAttachment(UploadedFile $file, int $senderId, int $recipientId): array
    {
        $path = $file->store('thread-'.$senderId.'-'.$recipientId, 'private_attachments');

        return $this->payload($file, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UploadedFile|TemporaryUploadedFile $file, string|false $path): array
    {
        return [
            'attachment_path' => $path ?: null,
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_mime_type' => $file instanceof TemporaryUploadedFile ? $file->getMimeType() : $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }
}
