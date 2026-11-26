<?php

declare(strict_types=1);

use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\GeolocationController;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\PageViewController;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\IdentifyController;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\BrowserEventController;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\TrustedOutcomeController;
use AIArmada\Signals\Actions\ServeSignalsTracker;
use AIArmada\Signals\Features\Ingestion\EntryPoints\Http\Middleware\VerifyTrustedSignalSignature;
use Illuminate\Support\Facades\Route;

Route::middleware(config('signals.http.middleware', ['api']))
    ->prefix(config('signals.http.prefix', 'api/signals'))
    ->group(function (): void {
        Route::get('/' . config('signals.http.tracker_script', 'tracker.js'), [ServeSignalsTracker::class, 'asController'])
            ->name('signals.tracker.script');

        Route::middleware('throttle:signals-collect')->group(function (): void {
            Route::post('/collect/identify', IdentifyController::class)
                ->name('signals.collect.identify');

            Route::post('/collect/browser-event', BrowserEventController::class)
                ->name('signals.collect.browser-event');

            Route::post('/collect/pageview', PageViewController::class)
                ->name('signals.collect.pageview');

            Route::post('/collect/geo', GeolocationController::class)
                ->name('signals.collect.geo');
        });

        Route::post('/collect/server-outcome', TrustedOutcomeController::class)
            ->middleware(VerifyTrustedSignalSignature::class)
            ->name('signals.collect.server-outcome');
    });
