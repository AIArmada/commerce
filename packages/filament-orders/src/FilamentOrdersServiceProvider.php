<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use AIArmada\FilamentOrders\EntryPoints\Http\Controllers\DownloadOrderInvoiceController;
use AIArmada\FilamentOrders\Persistence\FilamentOrdersCache;
use AIArmada\Orders\Models\Order;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentOrdersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-orders')
            ->hasConfigFile()
            ->hasViews('filament-orders');
    }

    public function bootingPackage(): void
    {
        Order::saved(static function (Order $order): void {
            FilamentOrdersCache::forgetForOrder($order);
        });

        Order::deleted(static function (Order $order): void {
            FilamentOrdersCache::forgetForOrder($order);
        });

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', FilamentAuthenticate::class, 'throttle:30,1'])
            ->group(function (): void {
                Route::get('/orders/{order}/invoice/download', DownloadOrderInvoiceController::class)->name('filament-orders.invoice.download');
            });
    }
}
