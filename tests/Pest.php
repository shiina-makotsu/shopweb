<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

beforeEach(function (): void {
    File::ensureDirectoryExists(storage_path('app'));
    File::put(storage_path('app/install.lock'), 'testing');
});
