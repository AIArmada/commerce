<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FilamentOrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-orders');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-orders'),
            ], 'filament-orders-views');
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web'])
            ->group(function (): void {
                Route::get('/orders/{order}/invoice/download', function (Order $order) {
                    return app(GenerateInvoice::class)->download($order);
                })->name('filament-orders.invoice.download');
            });
    }
}
