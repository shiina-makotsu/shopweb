<?php

namespace App\Filament\Resources\FriendLinkResource\Pages;

use App\Filament\Resources\FriendLinkResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateFriendLink extends CreateRecord
{
    protected static string $resource = FriendLinkResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return FriendLinkResource::normalizeImageFormData($data);
    }
}
