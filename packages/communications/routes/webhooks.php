<?php

declare(strict_types=1);

use AIArmada\Communications\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('communications.http.route_prefix', 'communications') . '/webhooks',
    'middleware' => config('communications.webhooks.middleware', ['api']),
    'name' => config('communications.webhooks.route_name_prefix', 'communications.webhooks.'),
], static function (): void {
    Route::post('{provider}', [WebhookController::class, 'handle']);
});
