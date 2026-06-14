<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\Concerns\HandlesPageCoverUpload;
use App\Support\PageMenuPublication;
use App\Support\PageTemplate;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use HandlesPageCoverUpload;

    protected static string $resource = PageResource::class;

    /**
     * @var array<string, mixed>
     */
    protected array $pendingMenuPublication = [];

    protected ?string $oldSlug = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PageMenuPublication::fill($this->getRecord(), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldSlug = $this->getRecord()->slug;
        $this->pendingMenuPublication = PageMenuPublication::extract($data);

        $data['template'] = PageTemplate::normalize($data['template'] ?? null);

        return $this->attachUploadedCover($data);
    }

    protected function afterSave(): void
    {
        PageMenuPublication::sync($this->record, $this->pendingMenuPublication, $this->oldSlug);
    }
}
