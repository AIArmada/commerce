<?php

declare(strict_types=1);

use AIArmada\Affiliates\Queries\AffiliateLookupQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\Support\Integrations\CartBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

it('hydrates a cookie attribution through the cart bridge', function (): void {
    config([
        'affiliates.cookies.enabled' => true,
        'affiliates.cookies.name' => 'affiliate_session',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'BRIDGE-' . Str::upper(Str::random(8)),
        'name' => 'Bridge Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $cookieValue = 'cookie-' . Str::lower(Str::random(16));

    AffiliateAttribution::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'cookie_value' => $cookieValue,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $request = Request::create('/', 'GET');
    $request->cookies->set('affiliate_session', $cookieValue);
    app()->instance('request', $request);

    $cart = app('cart')->getCurrentCart();
    $attribution = app(CartBridge::class)->hydrateAffiliateFromCookie($cart);

    expect($attribution)->not->toBeNull()
        ->and(app(AffiliateLookupQuery::class)->findAttachedAttribution($cart)?->affiliate_id)
        ->toBe($affiliate->getKey());
});

it('rejects forged and inactive cookie attributions', function (): void {
    config([
        'affiliates.cookies.enabled' => true,
        'affiliates.cookies.name' => 'affiliate_session',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'INACTIVE-' . Str::upper(Str::random(8)),
        'name' => 'Inactive Affiliate',
        'status' => Disabled::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $cookieValue = 'inactive-cookie-' . Str::lower(Str::random(16));

    AffiliateAttribution::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'cookie_value' => $cookieValue,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $request = Request::create('/', 'GET');
    $request->cookies->set('affiliate_session', 'forged-cookie-value');
    app()->instance('request', $request);

    $cart = app('cart')->getCurrentCart();
    $bridge = app(CartBridge::class);

    expect($bridge->hydrateAffiliateFromCookie($cart))->toBeNull();

    $request->cookies->set('affiliate_session', $cookieValue);

    expect($bridge->hydrateAffiliateFromCookie($cart))->toBeNull();
});


it('returns fresh immutable directory records after affiliate changes', function (): void {
    $affiliate = Affiliate::create(['name' => 'Initial directory name', 'code' => 'DIRECTORY-FRESH', 'status' => Active::class, 'commission_type' => 'percentage', 'commission_rate' => 500, 'currency' => 'MYR']);
    $directory = app(\AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory::class);
    $first = $directory->findById((string) $affiliate->getKey());
    expect($first)->toBeInstanceOf(\AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord::class);
    $affiliate->update(['name' => 'Updated directory name']);
    expect($directory->findById((string) $affiliate->getKey())?->name)->toBe('Updated directory name');
});
