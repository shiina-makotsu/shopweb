<?php

namespace App\Filament\Resources\FriendLinkResource\Pages;

use App\Filament\Resources\FriendLinkResource;
use Filament\Resources\Pages\EditRecord;

class EditFriendLink extends EditRecord
{
    protected static string $resource = FriendLinkResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FriendLinkResource::prepareImageFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FriendLinkResource::normalizeImageFormData($data);
    }
}
