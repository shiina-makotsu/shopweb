<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\ListRecords;

class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;

    public function mount(): void
    {
        $setting = SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name')]);

        $this->redirect(SiteSettingResource::getUrl('edit', ['record' => $setting]), navigate: false);
    }
}
