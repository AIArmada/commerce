<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Programs\EntryPoints\Http\ProgramCatalogController;
use AIArmada\Affiliates\Features\Programs\EntryPoints\Http\ProgramMembershipController;
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
            Route::get('programs', [ProgramCatalogController::class, 'index']);
            Route::get('programs/{id}/catalog', [ProgramCatalogController::class, 'show']);
            Route::post('{code}/programs/{id}/join', [ProgramMembershipController::class, 'join']);
            Route::get('{code}/programs/{id}/membership', [ProgramMembershipController::class, 'membership']);
        });
    });
