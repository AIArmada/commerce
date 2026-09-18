<?php

declare(strict_types=1);

use AIArmada\Links\Actions\RedirectToLink;
use Illuminate\Support\Facades\Route;

$attributes = [
    'middleware' => config('links.routing.middleware', ['web']),
    'prefix' => config('links.routing.prefix', 'go'),
];

$domain = config('links.routing.domain');

if (is_string($domain) && $domain !== '') {
    $attributes['domain'] = $domain;
}

Route::group($attributes, function (): void {
    Route::get('/{slug}', [RedirectToLink::class, 'asController'])
        ->where('slug', '[A-Za-z0-9_-]+')
        ->name(config('links.routing.name', 'links.redirect'));
});
