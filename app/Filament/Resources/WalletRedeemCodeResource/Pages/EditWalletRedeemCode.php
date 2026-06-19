<?php

namespace App\Filament\Resources\WalletRedeemCodeResource\Pages;

use App\Filament\Resources\WalletRedeemCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWalletRedeemCode extends EditRecord
{
    protected static string $resource = WalletRedeemCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
