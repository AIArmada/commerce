<?php

declare(strict_types=1);

use AIArmada\Affiliates\Http\Controllers\AffiliateApiController;
use AIArmada\Affiliates\Support\Middleware\EnsureApiAuthorized;
use AIArmada\CommerceSupport\Middleware\NeedsOwner;
use Illuminate\Support\Facades\Route;

if (! config('affiliates.api.enabled', false)) {
    return;
}

Route::prefix(config('affiliates.api.prefix', 'api/affiliates'))
    ->middleware(config('affiliates.api.middleware', ['api']))
    ->group(function (): void {
        $middleware = [EnsureApiAuthorized::class];

        if ((bool) config('affiliates.owner.enabled', false)) {
            $middleware[] = NeedsOwner::class;
        }

        Route::middleware($middleware)->group(function (): void {
            Route::get('{code}/summary', [AffiliateApiController::class, 'summary']);
            Route::post('{code}/links', [AffiliateApiController::class, 'links']);
            Route::get('{code}/creatives', [AffiliateApiController::class, 'creatives']);
        });
    });
