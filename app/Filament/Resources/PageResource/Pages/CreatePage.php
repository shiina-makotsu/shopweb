<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\Concerns\HandlesPageCoverUpload;
use App\Support\PageTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use HandlesPageCoverUpload;

    protected static string $resource = PageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['template'] = PageTemplate::normalize($data['template'] ?? null);

        if (blank($data['body'] ?? null)) {
            $data['body'] = PageTemplate::defaultBody($data['template']);
        }

        return $this->attachUploadedCover($data);
    }
}
