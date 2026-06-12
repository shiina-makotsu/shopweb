<?php

use Illuminate\Support\Facades\Route;

it('rewrites local absolute html urls to relative paths', function (): void {
    config(['app.url' => 'http://localhost']);

    Route::get('/__relative-html', fn () => response(
        '<a href="http://localhost/products">Products</a>'.
        '<a href="https://shop.example.test/cart?from=home#items">Cart</a>'.
        '<a href="https://external.example.test/page">External</a>',
        200,
        ['Content-Type' => 'text/html; charset=UTF-8'],
    ));

    $this
        ->get('http://shop.example.test/__relative-html')
        ->assertOk()
        ->assertSee('href="/products"', false)
        ->assertSee('href="/cart?from=home#items"', false)
        ->assertSee('https://external.example.test/page', false);
});

it('rewrites local redirect locations to relative paths', function (): void {
    config(['app.url' => 'http://localhost']);

    Route::get('/__relative-redirect', fn () => redirect('http://localhost/admin'));

    $this
        ->get('/__relative-redirect')
        ->assertRedirect('/admin');
});

it('rewrites auto injected livewire admin assets to relative paths', function (): void {
    config(['app.url' => 'http://origin.internal']);

    $this
        ->get('http://shop.example.test/admin/login')
        ->assertOk()
        ->assertSee('src="/livewire-', false)
        ->assertSee('data-module-url="/livewire-', false)
        ->assertSee('data-update-uri="/livewire-', false)
        ->assertDontSee('http://shop.example.test/livewire-', false)
        ->assertDontSee('http://origin.internal/livewire-', false);
});
