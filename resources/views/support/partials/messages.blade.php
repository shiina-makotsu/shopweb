<x-chat.messages
    mode="support"
    :session="$session"
    :messages="$session->messages"
    :mine-mode="$mineMode ?? 'customer'"
    :dark="$dark ?? false"
    :empty-text="$emptyText ?? '暂无消息。'"
/>
