<?php

namespace App\Http\Controllers;

use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
            'body' => ['required', 'string', 'max:2000'],
        ]);

        PrivateMessage::query()->create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'body' => $data['body'],
        ]);

        return back()->with('status', '消息已发送。');
    }
}
