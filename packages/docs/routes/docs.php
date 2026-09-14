<?php

declare(strict_types=1);

use AIArmada\Docs\Http\Controllers\DocShareController;
use AIArmada\Docs\Http\Controllers\DocTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:60,1'])->prefix('docs/track')->group(function (): void {
    Route::get('/open/{token}', [DocTrackingController::class, 'open'])->name('docs.track.open');
    Route::get('/click/{token}', [DocTrackingController::class, 'click'])->name('docs.track.click');
});

Route::middleware(['web', 'throttle:60,1'])->prefix('docs/share')->group(function (): void {
    Route::get('/{token}', [DocShareController::class, 'show'])->name('docs.share.show');
    Route::get('/{token}/pdf', [DocShareController::class, 'pdf'])->name('docs.share.pdf');
});
