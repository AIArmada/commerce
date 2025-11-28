<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\Http\Controllers\DocDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('filament-docs')
    ->name('filament-docs.')
    ->group(function (): void {
        Route::get('/download/{doc}', DocDownloadController::class)
            ->name('download');
    });
