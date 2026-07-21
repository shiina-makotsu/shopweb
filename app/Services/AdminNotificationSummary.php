<?php

namespace App\Services;

use App\Filament\Resources\AfterSalesRequestResource;
use App\Filament\Resources\ApprovalRequestResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\SupportChatSessionResource;
use App\Filament\Resources\SupportTicketResource;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AdminNotificationSummary
{
    private const CACHE_KEY = 'shop:admin:notification-summary';

    /** @var array<int, class-string> */
    private const RESOURCES = [
        OrderResource::class,
        ApprovalRequestResource::class,
        SupportChatSessionResource::class,
        SupportTicketResource::class,
        AfterSalesRequestResource::class,
    ];

    /** @return array{items:array<int,array{url:string,label:string,count:int,badge:string}>,groups:array<string,int>,updated_at:string} */
    public function data(bool $fresh = false): array
    {
        $cacheKey = self::CACHE_KEY.':'.(auth()->id() ?? 'guest');

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(5), fn (): array => $this->resolve());
    }

    /** @return array{items:array<int,array{url:string,label:string,count:int,badge:string}>,groups:array<string,int>,updated_at:string} */
    private function resolve(): array
    {
        $items = [];
        $groups = [];

        foreach (self::RESOURCES as $resource) {
            try {
                if (! $resource::canAccess()) {
                    continue;
                }

                $badge = (string) ($resource::getNavigationBadge() ?? '');
                $count = str_ends_with($badge, '+')
                    ? max(100, (int) preg_replace('/\D+/', '', $badge))
                    : max(0, (int) preg_replace('/\D+/', '', $badge));
                $group = $resource::getNavigationGroup();
                $groupLabel = $group instanceof \UnitEnum ? $group->name : (string) $group;

                $items[] = [
                    'url' => $resource::getUrl(),
                    'label' => $resource::getNavigationLabel(),
                    'count' => $count,
                    'badge' => $badge !== '' ? $badge : '0',
                ];

                if ($groupLabel !== '') {
                    $groups[$groupLabel] = ($groups[$groupLabel] ?? 0) + $count;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'items' => $items,
            'groups' => $groups,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
