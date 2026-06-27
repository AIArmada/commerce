<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from payment gateways. Each gateway
| can have its own webhook endpoint for processing events.
|
*/

Route::middleware('api')->prefix('cashier')->name('cashier.')->group(function (): void {});
