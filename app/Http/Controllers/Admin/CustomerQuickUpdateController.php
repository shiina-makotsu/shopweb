<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\CustomerResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerQuickUpdateController extends Controller
{
    public function update(Request $request, User $customer, AdminActivityLogger $activity): RedirectResponse
    {
        abort_unless($customer->role === 'customer', 404);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'has_diagnosis_certificate' => ['nullable', 'boolean'],
            'account_type' => ['required', 'string', Rule::in(['regular', 'member'])],
            'forum_role' => ['required', 'string', Rule::in(['member', 'moderator'])],
            'forum_posting_banned_at' => ['nullable', 'date'],
            'forum_posting_ban_reason' => ['nullable', 'string', 'max:255'],
            'can_view_order_numbers' => ['nullable', 'boolean'],
            'can_view_tracking_numbers' => ['nullable', 'boolean'],
        ]);

        $data['has_diagnosis_certificate'] = (bool) ($data['has_diagnosis_certificate'] ?? false);

        if (! ($request->user()?->isSuperAdmin() ?? false)) {
            unset($data['can_view_order_numbers'], $data['can_view_tracking_numbers']);
        } else {
            $data['can_view_order_numbers'] = (bool) ($data['can_view_order_numbers'] ?? false);
            $data['can_view_tracking_numbers'] = (bool) ($data['can_view_tracking_numbers'] ?? false);
        }

        CustomerResource::recordProfileChanges($customer, $data, $request->user());

        $changes = [];

        foreach ($data as $field => $value) {
            $old = $customer->getAttribute($field);

            if ((string) $old === (string) $value) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $value];
        }

        if ($changes !== []) {
            $customer->update($data);

            $activity->log('customer_quick_updated', $customer->fresh(), '前台用户快速详情更新', [
                'customer_id' => $customer->id,
                'public_id' => $customer->public_id,
                'changes' => $changes,
            ], $request->user());
        }

        return back();
    }
}
