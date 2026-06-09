<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:shop', function (): void {
    $this->info('ShopWeb lightweight storefront');
})->purpose('Show ShopWeb information');
