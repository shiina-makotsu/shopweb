<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserProfileChangeLog;
use App\Services\AiUsageService;
use App\Services\CouponService;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserCenterController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $user->ensurePublicId();

        $orderCounts = Order::query()
            ->whereBelongsTo($user)
            ->whereNull('user_deleted_at')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('user.center', [
            'user' => $user->loadCount(['wishlists', 'favorites']),
            'recentHistories' => $user->browsingHistories()
                ->with('product.coverMedia')
                ->latest('viewed_at')
                ->limit(8)
                ->get(),
            'orderCounts' => $orderCounts,
            'pendingPaymentCount' => (int) ($orderCounts[Order::STATUS_PENDING_PAYMENT] ?? 0),
            'pendingShipmentCount' => (int) ($orderCounts[Order::STATUS_PENDING_SHIPMENT] ?? 0) + (int) ($orderCounts[Order::STATUS_INCOMING] ?? 0),
            'awaitingReceiptCount' => (int) ($orderCounts[Order::STATUS_SHIPPED] ?? 0) + (int) ($orderCounts[Order::STATUS_AWAITING_RECEIPT] ?? 0),
            'fulfilledCount' => (int) ($orderCounts[Order::STATUS_FULFILLED] ?? 0),
            'privateUnreadCount' => $this->privateUnreadCount($user),
        ]);
    }

    public function section(Request $request, string $section): View|RedirectResponse
    {
        $user = $request->user();
        $user->ensurePublicId();

        $allowed = ['profile', 'wishlists', 'favorites', 'addresses', 'coupons', 'chat', 'ai', 'privacy', 'interface', 'membership'];
        abort_unless(in_array($section, $allowed, true), 404);

        $aiUsage = app(AiUsageService::class);

        return view('user.section', [
            'user' => $user,
            'section' => $section,
            'wishlists' => $section === 'wishlists'
                ? $user->wishlists()->with(['product.coverMedia', 'product.variants'])->latest()->paginate(12)
                : null,
            'favorites' => $section === 'favorites'
                ? $user->favorites()->with(['product.coverMedia', 'product.variants'])->latest()->paginate(12)
                : null,
            'addresses' => $section === 'addresses'
                ? $user->addresses()->latest()->get()
                : null,
            'coupons' => $section === 'coupons'
                ? $user->coupons()->with(['coupon.products', 'coupon.product'])->latest()->get()
                : null,
            'privateUnreadCount' => $this->privateUnreadCount($user),
            'chatThreads' => $section === 'chat'
                ? $this->chatThreads($user)
                : collect(),
            'aiQuota' => $section === 'ai'
                ? [
                    'quota_unlimited' => ! $aiUsage->shouldEnforceQuota($user),
                    'limit_k' => $aiUsage->quotaLimitK($user),
                    'remaining_k' => $aiUsage->remainingK($user),
                    'total_tokens' => $aiUsage->usedTokens($user),
                    'tokens_24h' => $aiUsage->usedTokens($user, now()->subDay()),
                    'model_breakdown' => $aiUsage->modelBreakdown($user),
                    'recent_logs' => $aiUsage->recentLogs($user),
                ]
                : null,
        ]);
    }

    private function privateUnreadCount(User $user): int
    {
        return PrivateMessage::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user: \App\Models\User, last_message: \App\Models\PrivateMessage, unread_count: int}>
     */
    private function chatThreads(User $user): Collection
    {
        return PrivateMessage::query()
            ->with(['sender', 'recipient'])
            ->where(function ($query) use ($user): void {
                $query->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->latest()
            ->limit(300)
            ->get()
            ->groupBy(fn (PrivateMessage $message): int => $message->sender_id === $user->id ? $message->recipient_id : $message->sender_id)
            ->map(function (Collection $messages) use ($user): array {
                /** @var \App\Models\PrivateMessage $lastMessage */
                $lastMessage = $messages->sortByDesc('created_at')->first();
                $otherUser = $lastMessage->sender_id === $user->id ? $lastMessage->recipient : $lastMessage->sender;

                return [
                    'user' => $otherUser,
                    'last_message' => $lastMessage,
                    'unread_count' => $messages
                        ->where('recipient_id', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                ];
            })
            ->filter(fn (array $thread): bool => $thread['user'] instanceof User)
            ->sortByDesc(fn (array $thread) => $thread['last_message']->created_at)
            ->values();
    }

    public function storeCoupon(Request $request, CouponService $coupons): RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:100'],
        ]);

        $coupons->claimByCode($data['coupon_code'], $request->user());

        return back()->with('status', '优惠码已添加。');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'profile_intro' => ['nullable', 'string', 'max:1000'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'has_diagnosis_certificate' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'avatar_cropped' => ['nullable', 'string'],
        ]);

        $data['has_diagnosis_certificate'] = (bool) ($data['has_diagnosis_certificate'] ?? false);
        $croppedAvatarPath = $this->storeCroppedAvatar((string) ($data['avatar_cropped'] ?? ''));

        if ($croppedAvatarPath || $request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public_uploads')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $croppedAvatarPath ?: $request->file('avatar')->store('avatars', 'public_uploads');
        }

        unset($data['avatar'], $data['avatar_cropped']);

        $this->recordProfileChanges($user, $data, $user, 'user');
        $user->update($data);

        return back()->with('status', '个人资料已更新。');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordProfileChanges(User $user, array $data, ?User $changedBy, string $source): void
    {
        foreach (['birthday', 'has_diagnosis_certificate'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = $this->profileLogValue($user->{$field});
            $new = $this->profileLogValue($data[$field]);

            if ($old === $new) {
                continue;
            }

            UserProfileChangeLog::query()->create([
                'user_id' => $user->id,
                'changed_by_id' => $changedBy?->id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'source' => $source,
            ]);
        }
    }

    private function profileLogValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function storeCroppedAvatar(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/(png|jpeg|webp);base64,(?<data>.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches['data'], true);

        if ($binary === false || strlen($binary) > 6 * 1024 * 1024) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $path = 'avatars/'.Str::uuid().'.'.$extension;
        Storage::disk('public_uploads')->put($path, $binary);

        return $path;
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validateAddress($request->all());
        $data['is_default'] = (bool) ($data['is_default'] ?? false) || ! $user->addresses()->exists();
        $data['is_visible'] = (bool) ($data['is_visible'] ?? false);

        if ($data['is_default']) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create($data);

        return back()->with('status', '地址已保存。');
    }

    public function updateAddress(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $data = $this->validateAddress($request->all());
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['is_visible'] = (bool) ($data['is_visible'] ?? false);

        if ($data['is_default']) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return back()->with('status', '地址已更新。');
    }

    public function setDefaultAddress(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('status', '默认地址已更新。');
    }

    public function destroyAddress(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $address->delete();

        if (! $request->user()->addresses()->where('is_default', true)->exists()) {
            $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', '地址已删除。');
    }

    private function validateAddress(array $input): array
    {
        $input = $this->fillAddressFromRawText($input);

        return Validator::make($input, [
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:60'],
            'country' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'raw_text' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
        ])->validate();
    }

    private function fillAddressFromRawText(array $input): array
    {
        $raw = trim((string) ($input['raw_text'] ?? ''));

        if ($raw === '') {
            return $input;
        }

        $parsed = $this->parseChineseAddress($raw);

        foreach (['country', 'province', 'city', 'district', 'street'] as $field) {
            if (blank($input[$field] ?? null) && filled($parsed[$field] ?? null)) {
                $input[$field] = $parsed[$field];
            }
        }

        return $input;
    }

    /**
     * @return array{country?: string, province?: string, city?: string, district?: string, street?: string}
     */
    private function parseChineseAddress(string $raw): array
    {
        $text = preg_replace('/\s+/', '', $raw) ?: $raw;
        $parsed = ['country' => str_contains($text, '中国') ? '中国' : '中国'];
        $text = preg_replace('/^中国/u', '', $text) ?: $text;

        if (preg_match('/(?P<province>[^省市区县]{2,}(?:省|自治区|特别行政区)|[^省市区县]{2,}市)/u', $text, $match, PREG_OFFSET_CAPTURE)) {
            $parsed['province'] = $match['province'][0];
            $text = substr($text, (int) ($match['province'][1] + strlen($match['province'][0])));
        }

        if (preg_match('/(?P<city>[^省市区县]{2,}(?:市|自治州|地区|盟))/u', $text, $match, PREG_OFFSET_CAPTURE)) {
            $parsed['city'] = $match['city'][0];
            $text = substr($text, (int) ($match['city'][1] + strlen($match['city'][0])));
        } elseif (isset($parsed['province']) && str_ends_with($parsed['province'], '市')) {
            $parsed['city'] = $parsed['province'];
        }

        if (preg_match('/(?P<district>[^省市区县]{2,}(?:区|县|旗|市))/u', $text, $match, PREG_OFFSET_CAPTURE)) {
            $parsed['district'] = $match['district'][0];
            $text = substr($text, (int) ($match['district'][1] + strlen($match['district'][0])));
        }

        if (trim($text) !== '') {
            $parsed['street'] = trim($text);
        }

        return $parsed;
    }
}
