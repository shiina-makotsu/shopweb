<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->cleanupGlobalCouponProductLinks();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('referralRewards')
                ->label('邀请奖励设置')
                ->url(\App\Filament\Resources\ReferralRewardRuleResource::getUrl('index')),
            CouponResource::issueCouponHeaderAction(),
            CreateAction::make(),
        ];
    }

    private function cleanupGlobalCouponProductLinks(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        $globalCouponIds = DB::table('coupons')
            ->where('scope', Coupon::SCOPE_GLOBAL)
            ->pluck('id');

        if (Schema::hasColumn('coupons', 'product_id')) {
            DB::table('coupons')
                ->where('scope', Coupon::SCOPE_GLOBAL)
                ->whereNotNull('product_id')
                ->update(['product_id' => null]);
        }

        if (Schema::hasTable('coupon_product') && $globalCouponIds->isNotEmpty()) {
            DB::table('coupon_product')
                ->whereIn('coupon_id', $globalCouponIds)
                ->delete();
        }
    }
}
