<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Middleware\NeedsOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerRouteBinding;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Http\Controllers\DocDownloadController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$middleware = ['web', 'auth'];

if ((bool) config('docs.owner.enabled', false)) {
    Route::bind('doc', static function (string $value): Doc | string {
        if (OwnerContext::resolve() === null && ! OwnerContext::isExplicitGlobal()) {
            return $value;
        }

        try {
            return OwnerRouteBinding::resolve(
                modelClass: Doc::class,
                value: $value,
                includeGlobal: (bool) config('docs.owner.include_global', false),
            );
        } catch (AuthorizationException) {
            throw new NotFoundHttpException('Document not found.');
        }
    });

    $middleware[] = NeedsOwner::class;
}

Route::middleware($middleware)
    ->prefix('filament-docs')
    ->name('filament-docs.')
    ->group(function (): void {
        Route::get('/download/{doc}', DocDownloadController::class)
            ->name('download');
    });
