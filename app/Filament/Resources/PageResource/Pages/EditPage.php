<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\Concerns\HandlesPageCoverUpload;
use App\Support\PageTemplate;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use HandlesPageCoverUpload;

    protected static string $resource = PageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['template'] = PageTemplate::normalize($data['template'] ?? null);

        return $this->attachUploadedCover($data);
    }
}
