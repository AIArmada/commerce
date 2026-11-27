<?php

declare(strict_types=1);

use AIArmada\Links\EntryPoints\Http\RedirectToLinkController;
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
    Route::get('/{prefix}/{slug}', [RedirectToLinkController::class, 'branded'])
        ->where('prefix', '[a-z0-9]+(?:[-_][a-z0-9]+)*')
        ->where('slug', '[A-Za-z0-9_-]+')
        ->name(config('links.routing.name', 'links.redirect') . '.branded');

    Route::get('/{slug}', [RedirectToLinkController::class, 'redirect'])
        ->where('slug', '[A-Za-z0-9_-]+')
        ->name(config('links.routing.name', 'links.redirect'));
});
