<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;

abstract class CreateRecord extends \Filament\Resources\Pages\CreateRecord
{
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('保存');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('保存并创建新' . $this->getCreateAnotherModelLabel());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl();
    }

    protected function getCreateAnotherModelLabel(): string
    {
        $resource = static::getResource();
        $label = $resource::getModelLabel();

        return filled($label) ? $label : '记录';
    }
}
