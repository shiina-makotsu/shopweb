<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;

abstract class EditRecord extends \Filament\Resources\Pages\EditRecord
{
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('保存');
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }
}
