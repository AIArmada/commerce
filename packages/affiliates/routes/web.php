<?php

declare(strict_types=1);

use AIArmada\Affiliates\Http\Controllers\PublicAffiliateReferralController;
use Illuminate\Support\Facades\Route;

if (! config('affiliates.public_pages.enabled', true) || ! config('affiliates.public_pages.route.enabled', true)) {
    return;
}

$routePath = (string) config('affiliates.public_pages.route.path', 'r/{affiliateCode}');
$routeName = (string) config('affiliates.public_pages.route.name', 'affiliate.referral.entry');
$routeMiddleware = (array) config('affiliates.public_pages.route.middleware', ['web']);

Route::middleware($routeMiddleware)->group(function () use ($routeName, $routePath): void {
    Route::get($routePath, PublicAffiliateReferralController::class)->name($routeName);
});