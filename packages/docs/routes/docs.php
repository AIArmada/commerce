<?php

declare(strict_types=1);

use AIArmada\Docs\Http\Controllers\DocTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('docs/track')->group(function (): void {
    Route::get('/open/{token}', [DocTrackingController::class, 'open'])->name('docs.track.open');
    Route::get('/click/{token}', [DocTrackingController::class, 'click'])->name('docs.track.click');
});
