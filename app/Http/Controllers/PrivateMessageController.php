<?php

namespace App\Http\Controllers;

use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMessageController extends Controller
{
    public function thread(Request $request, User $user): View
    {
        abort_if($request->user()->id === $user->id, 404);

        PrivateMessage::query()
            ->where('sender_id', $user->id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('messages.thread', [
            'otherUser' => $user,
            'messages' => PrivateMessage::query()
                ->where(function ($query) use ($request, $user): void {
                    $query->where('sender_id', $request->user()->id)->where('recipient_id', $user->id);
                })
                ->orWhere(function ($query) use ($request, $user): void {
                    $query->where('sender_id', $user->id)->where('recipient_id', $request->user()->id);
                })
                ->oldest()
                ->get(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 404);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);
        $body = trim((string) ($data['body'] ?? ''));

        abort_if($body === '' && ! $request->hasFile('attachment'), 422);

        $message = PrivateMessage::query()->create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'body' => $body,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('thread-'.$request->user()->id.'-'.$user->id, 'private_attachments');
            $message->update([
                'attachment_path' => $path,
                'attachment_original_name' => $file->getClientOriginalName(),
                'attachment_mime_type' => $file->getClientMimeType(),
                'attachment_size' => $file->getSize(),
            ]);
        }

        return back()->with('status', '消息已发送。');
    }

    public function attachment(Request $request, PrivateMessage $message): StreamedResponse|Response
    {
        abort_unless(in_array($request->user()->id, [$message->sender_id, $message->recipient_id], true), 403);

        $disk = Storage::disk('private_attachments');
        abort_unless($message->attachment_path && $disk->exists($message->attachment_path), 404);

        return $disk->response($message->attachment_path, $message->attachment_original_name);
    }
}
