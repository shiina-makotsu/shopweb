<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('trusts forwarded host and scheme headers from configured proxies', function (): void {
    config(['trustedproxy.proxies' => '*']);

    Route::get('/__proxy-check', fn (Request $request): array => [
        'host' => $request->getHost(),
        'scheme' => $request->getScheme(),
        'secure' => $request->isSecure(),
        'url' => $request->url(),
    ])->middleware('web');

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '2606:4700:4700::1111',
            'HTTP_X_FORWARDED_HOST' => 'shop.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])
        ->get('http://origin.internal/__proxy-check')
        ->assertOk()
        ->assertJson([
            'host' => 'shop.example.test',
            'scheme' => 'https',
            'secure' => true,
            'url' => 'https://shop.example.test/__proxy-check',
        ]);
});
