<?php

namespace App\Filament\Resources\WalletRedeemCodeResource\Pages;

use App\Filament\Pages\WalletSettingsPage;
use App\Filament\Resources\WalletRedeemCodeResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Resources\Pages\CreateRecord;

class CreateWalletRedeemCode extends CreateRecord
{
    protected static string $resource = WalletRedeemCodeResource::class;

    protected function getHeaderActions(): array
    {
        return AdminPageTabs::actions(WalletSettingsPage::tabs(), 'redeem_codes');
    }
}
