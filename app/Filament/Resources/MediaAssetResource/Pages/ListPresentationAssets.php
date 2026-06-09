<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListPresentationAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    public function getTitle(): string
    {
        return 'PPT/展示资料';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('上传展示资料')
                ->mutateDataUsing(function (array $data): array {
                    $data['usage'] = MediaAsset::USAGE_PRESENTATION;

                    return $data;
                }),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()?->where('usage', MediaAsset::USAGE_PRESENTATION);
    }
}
