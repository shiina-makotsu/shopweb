<?php

namespace App\Filament\Resources\WalletRedeemCodeResource\Pages;

use App\Filament\Pages\WalletSettingsPage;
use App\Filament\Resources\WalletRedeemCodeResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWalletRedeemCodes extends ListRecords
{
    protected static string $resource = WalletRedeemCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AdminPageTabs::actions(WalletSettingsPage::tabs(), 'redeem_codes'),
            CreateAction::make(),
        ];
    }
}
