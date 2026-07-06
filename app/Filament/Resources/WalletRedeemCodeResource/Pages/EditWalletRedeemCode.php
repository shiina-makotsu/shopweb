<?php

namespace App\Filament\Resources\WalletRedeemCodeResource\Pages;

use App\Filament\Pages\WalletSettingsPage;
use App\Filament\Resources\WalletRedeemCodeResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWalletRedeemCode extends EditRecord
{
    protected static string $resource = WalletRedeemCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AdminPageTabs::actions(WalletSettingsPage::tabs(), 'redeem_codes'),
            DeleteAction::make(),
        ];
    }
}
