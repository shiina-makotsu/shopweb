<?php

namespace App\Filament\Resources\WalletRedeemCodeResource\Pages;

use App\Filament\Resources\WalletRedeemCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWalletRedeemCodes extends ListRecords
{
    protected static string $resource = WalletRedeemCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
