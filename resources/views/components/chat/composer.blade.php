@props([
    'mode' => 'form',
    'action' => null,
    'wireSubmit' => null,
    'messageName' => 'message',
    'messageModel' => null,
    'attachmentName' => 'attachment',
    'attachmentModel' => null,
    'submitLabel' => '发送',
    'placeholder' => '输入消息...',
    'disabled' => false,
    'hidden' => [],
    'value' => '',
    'dark' => false,
])

@php
    $composerId = 'chat-composer-'.\Illuminate\Support\Str::uuid();
    $formClass = 'chat-composer border-t px-4 py-3 text-sm '.($dark ? 'border-gray-800 bg-gray-900' : 'border-slate-200 bg-white');
    $inputClass = 'min-h-11 flex-1 resize-none rounded-2xl border px-4 py-2.5 text-sm leading-6 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 '.($dark ? 'border-gray-700 bg-gray-950 text-gray-100 placeholder:text-gray-500' : 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400');
    $iconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border-0 bg-transparent transition disabled:cursor-not-allowed disabled:opacity-50 '.($dark ? 'text-gray-300 hover:bg-gray-800 hover:text-blue-300' : 'text-slate-600 hover:bg-slate-100 hover:text-blue-700');
    $sendClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border-0 bg-blue-700 text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60';
    $iconButtonStyle = 'display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border:0;background:transparent;border-radius:8px;cursor:pointer;color:currentColor;';
    $sendStyle = 'display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border:0;background:#1d4ed8;color:#fff;border-radius:8px;cursor:pointer;';
    $menuClass = 'absolute bottom-12 left-0 z-40 hidden w-48 rounded-2xl border p-2 shadow-xl '.($dark ? 'border-gray-700 bg-gray-950 text-gray-100' : 'border-slate-200 bg-white text-slate-900');
    $menuItemClass = 'flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition '.($dark ? 'hover:bg-gray-800' : 'hover:bg-slate-100');
    $emojiItems = ['😀', '😂', '😊', '😍', '😭', '😡', '👍', '🙏', '🎉', '❤️', 'OK', '收到'];
@endphp

@if($mode === 'livewire')
    <form wire:submit="{{ $wireSubmit }}" class="{{ $formClass }}" data-chat-composer="{{ $composerId }}" enctype="multipart/form-data">
@else
    <form method="post" action="{{ $action }}" enctype="multipart/form-data" class="{{ $formClass }}" data-chat-composer="{{ $composerId }}">
        @csrf
        @foreach($hidden as $name => $hiddenValue)
            <input type="hidden" name="{{ $name }}" value="{{ $hiddenValue }}">
        @endforeach
@endif
    {{ $slot }}

    <div class="flex items-end gap-2">
        <div class="relative shrink-0">
            <button class="{{ $iconButtonClass }}" style="{{ $iconButtonStyle }}" type="button" data-chat-attach-toggle title="添加附件" aria-label="添加附件" @disabled($disabled)>
                <i class="fa-solid fa-paperclip" style="font-size:22px;" aria-hidden="true"></i>
            </button>

            <div class="{{ $menuClass }}" data-chat-attach-panel>
                <label class="{{ $menuItemClass }}">
                    <i class="fa-regular fa-image fa-fw" aria-hidden="true"></i>
                    <span>图片或视频</span>
                    @if($mode === 'livewire')
                        <input class="hidden" type="file" accept="image/*,video/*" @if($attachmentModel) wire:model="{{ $attachmentModel }}" @endif @disabled($disabled)>
                    @else
                        <input class="hidden" type="file" accept="image/*,video/*" name="{{ $attachmentName }}" @disabled($disabled)>
                    @endif
                </label>

                <label class="{{ $menuItemClass }}">
                    <i class="fa-regular fa-file fa-fw" aria-hidden="true"></i>
                    <span>文件</span>
                    @if($mode === 'livewire')
                        <input class="hidden" type="file" @if($attachmentModel) wire:model="{{ $attachmentModel }}" @endif @disabled($disabled)>
                    @else
                        <input class="hidden" type="file" name="{{ $attachmentName }}" @disabled($disabled)>
                    @endif
                </label>

                <button class="{{ $menuItemClass }}" type="button" data-chat-location @disabled($disabled)>
                    <i class="fa-solid fa-location-dot fa-fw" aria-hidden="true"></i>
                    <span>位置</span>
                </button>
            </div>
        </div>

        <div class="relative shrink-0">
            <button class="{{ $iconButtonClass }}" style="{{ $iconButtonStyle }}" type="button" data-chat-emoji-toggle aria-label="选择表情" title="选择表情" @disabled($disabled)>
                <i class="fa-regular fa-face-smile" style="font-size:22px;" aria-hidden="true"></i>
            </button>
            <div class="absolute bottom-12 left-0 z-30 hidden w-56 rounded-2xl border p-2 shadow-xl {{ $dark ? 'border-gray-700 bg-gray-950' : 'border-slate-200 bg-white' }}" data-chat-emoji-panel>
                <div class="grid grid-cols-4 gap-1">
                    @foreach($emojiItems as $emoji)
                        <button class="rounded-xl px-2 py-1.5 text-sm transition {{ $dark ? 'text-gray-100 hover:bg-gray-800' : 'text-slate-700 hover:bg-blue-50' }}" type="button" data-chat-emoji="{{ $emoji }}">{{ $emoji }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <textarea
            class="{{ $inputClass }}"
            style="max-height: 144px; overflow-y: auto;"
            @if($mode === 'livewire' && $messageModel) wire:model="{{ $messageModel }}" @endif
            @if($mode !== 'livewire') name="{{ $messageName }}" @endif
            maxlength="3000"
            rows="1"
            placeholder="{{ $placeholder }}"
            data-chat-textarea
            @disabled($disabled)
        >{{ $mode === 'livewire' ? '' : $value }}</textarea>

        <button class="{{ $sendClass }}" style="{{ $sendStyle }}" type="submit" title="{{ $submitLabel }}" aria-label="{{ $submitLabel }}" @disabled($disabled)>
            <i class="fa-solid fa-paper-plane" style="font-size:20px;" aria-hidden="true"></i>
        </button>
    </div>

    <div class="mt-2 hidden items-center gap-2 text-xs {{ $dark ? 'text-gray-400' : 'text-slate-500' }}" data-chat-file-name></div>
</form>

@once
    <script>
        (() => {
            const resize = (textarea) => {
                if (!textarea) return;
                const oneLineHeight = 44;
                const lineHeight = 24;
                const logicalLines = Math.max(1, textarea.value.split('\n').length);
                const nextHeight = Math.min(oneLineHeight + (logicalLines - 1) * lineHeight, 144);
                textarea.style.height = `${nextHeight}px`;
            };

            const resizeAll = () => {
                document.querySelectorAll('[data-chat-textarea]').forEach(resize);
            };

            const insertText = (textarea, text) => {
                if (!textarea) return;
                const start = textarea.selectionStart ?? textarea.value.length;
                const end = textarea.selectionEnd ?? textarea.value.length;
                textarea.value = `${textarea.value.slice(0, start)}${text}${textarea.value.slice(end)}`;
                textarea.selectionStart = textarea.selectionEnd = start + text.length;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.focus();
            };

            const closePanels = (exceptComposer = null) => {
                document.querySelectorAll('[data-chat-attach-panel], [data-chat-emoji-panel]').forEach((panel) => {
                    if (exceptComposer && panel.closest('[data-chat-composer]') === exceptComposer) return;
                    panel.classList.add('hidden');
                });
            };

            const isMobileInput = () => {
                return window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(max-width: 768px)').matches;
            };

            document.addEventListener('input', (event) => {
                if (event.target.matches('[data-chat-textarea]')) {
                    resize(event.target);
                }
            });

            document.addEventListener('focusin', (event) => {
                if (event.target.matches('[data-chat-textarea]')) {
                    resize(event.target);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (!event.target.matches('[data-chat-textarea]') || event.key !== 'Enter' || event.isComposing) return;

                if (isMobileInput()) {
                    window.setTimeout(() => resize(event.target), 0);
                    return;
                }

                if (event.ctrlKey) {
                    event.preventDefault();
                    insertText(event.target, '\n');
                    resize(event.target);
                    return;
                }

                event.preventDefault();
                event.target.closest('form')?.requestSubmit();
            });

            document.addEventListener('change', (event) => {
                if (!event.target.matches('[data-chat-composer] input[type="file"]')) return;

                const composer = event.target.closest('[data-chat-composer]');
                const label = composer?.querySelector('[data-chat-file-name]');
                const file = event.target.files?.[0];
                composer?.querySelector('[data-chat-attach-panel]')?.classList.add('hidden');
                if (!label || !file) return;
                label.textContent = `已选择：${file.name}`;
                label.classList.remove('hidden');
                label.classList.add('flex');
            });

            document.addEventListener('click', (event) => {
                const attachToggle = event.target.closest('[data-chat-attach-toggle]');
                const emojiToggle = event.target.closest('[data-chat-emoji-toggle]');
                const emojiButton = event.target.closest('[data-chat-emoji]');
                const locationButton = event.target.closest('[data-chat-location]');
                const composer = event.target.closest('[data-chat-composer]');

                if (!attachToggle && !emojiToggle && !emojiButton && !locationButton && !event.target.closest('[data-chat-attach-panel]')) {
                    closePanels();
                }

                if (attachToggle) {
                    const currentComposer = attachToggle.closest('[data-chat-composer]');
                    closePanels(currentComposer);
                    currentComposer?.querySelector('[data-chat-emoji-panel]')?.classList.add('hidden');
                    currentComposer?.querySelector('[data-chat-attach-panel]')?.classList.toggle('hidden');
                    return;
                }

                if (emojiToggle) {
                    const currentComposer = emojiToggle.closest('[data-chat-composer]');
                    closePanels(currentComposer);
                    currentComposer?.querySelector('[data-chat-attach-panel]')?.classList.add('hidden');
                    currentComposer?.querySelector('[data-chat-emoji-panel]')?.classList.toggle('hidden');
                    return;
                }

                if (emojiButton) {
                    const currentComposer = emojiButton.closest('[data-chat-composer]');
                    insertText(currentComposer?.querySelector('[data-chat-textarea]'), emojiButton.dataset.chatEmoji || '');
                    currentComposer?.querySelector('[data-chat-emoji-panel]')?.classList.add('hidden');
                    return;
                }

                if (!locationButton) return;

                const currentComposer = locationButton.closest('[data-chat-composer]');
                const textarea = currentComposer?.querySelector('[data-chat-textarea]');
                currentComposer?.querySelector('[data-chat-attach-panel]')?.classList.add('hidden');

                if (!navigator.geolocation) {
                    insertText(textarea, '位置：');
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude.toFixed(6);
                        const lng = position.coords.longitude.toFixed(6);
                        insertText(textarea, `位置：https://maps.google.com/?q=${lat},${lng}`);
                    },
                    () => insertText(textarea, '位置：'),
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 },
                );
            });

            document.addEventListener('livewire:navigated', resizeAll);
            document.addEventListener('livewire:update', resizeAll);
            document.addEventListener('livewire:updated', resizeAll);

            if (window.MutationObserver) {
                let resizeScheduled = false;
                new MutationObserver(() => {
                    if (resizeScheduled) return;
                    resizeScheduled = true;
                    window.requestAnimationFrame(() => {
                        resizeScheduled = false;
                        resizeAll();
                    });
                }).observe(document.body, { childList: true, subtree: true });
            }

            window.setInterval(() => {
                const active = document.activeElement;
                if (active?.matches?.('[data-chat-textarea]')) {
                    resize(active);
                }
            }, 1000);

            resizeAll();
        })();
    </script>
@endonce
