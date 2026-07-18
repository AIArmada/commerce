<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\ResolvePublicAffiliateReferralContext;
use AIArmada\Affiliates\Actions\Affiliates\TrackAffiliateVisit;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'PUBLIC-AFF-' . uniqid(),
        'name' => 'Public Page Partner',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
        'default_voucher_code' => 'REFPUBLIC' . uniqid(),
    ]);
});

test('package public referral route captures visits and redirects home', function (): void {
    $response = $this->get('/r/' . $this->affiliate->code);

    $response
        ->assertRedirect(url('/'))
        ->assertCookie((string) config('affiliates.cookies.name', 'affiliate_session'));

    $attribution = AffiliateAttribution::query()->sole();

    expect($attribution->affiliate_code)->toBe($this->affiliate->code)
        ->and(data_get($attribution->metadata, 'entry_route'))->toBe('affiliate.referral.path');
});

test('package public referral route preserves query parameters on redirect', function (): void {
    $response = $this->get('/r/' . $this->affiliate->code . '?' . (string) config('affiliates.public_pages.route.destination_parameter', 'to') . '=checkout');

    $response->assertRedirect(url('/?to=checkout'));
});

test('package resolves public referral context from query parameters', function (): void {
    $request = Request::create('/?aff=' . $this->affiliate->code, 'GET');
    app()->instance('request', $request);

    $context = app(ResolvePublicAffiliateReferralContext::class)->handle($request);

    expect($context)
        ->not()->toBeNull()
        ->and($context['code'])->toBe($this->affiliate->code)
        ->and($context['name'])->toBe($this->affiliate->name)
        ->and($context['source'])->toBe('query')
        ->and($context['home_url'])->toBe(url('/?aff=' . $this->affiliate->code))
        ->and($context['checkout_url'])->toBe(url('/checkout?aff=' . $this->affiliate->code));
});

test('package resolves public referral context from encrypted cookies', function (): void {
    app(TrackAffiliateVisit::class)->handle($this->affiliate->code, [], 'public-cookie-value');

    $request = Request::create('/', 'GET');
    $request->cookies->set(
        (string) config('affiliates.cookies.name', 'affiliate_session'),
        Crypt::encryptString('public-cookie-value'),
    );

    app()->instance('request', $request);

    $context = app(ResolvePublicAffiliateReferralContext::class)->handle($request);

    expect($context)
        ->not()->toBeNull()
        ->and($context['code'])->toBe($this->affiliate->code)
        ->and($context['source'])->toBe('cookie');
});

test('package view composer shares public referral context without relying on middleware hydration', function (): void {
    $request = Request::create('/?aff=' . $this->affiliate->code, 'GET');
    app()->instance('request', $request);

    $content = View::make('affiliates::components.public-referral-banner', [
        'showCheckoutLink' => true,
    ])->render();

    expect($content)
        ->toContain('Referral Applied')
        ->toContain($this->affiliate->name)
        ->toContain($this->affiliate->default_voucher_code)
        ->toContain(url('/checkout?aff=' . $this->affiliate->code));
});

test('package public referral banner renders through the Blade component tag', function (): void {
    $request = Request::create('/?aff=' . $this->affiliate->code, 'GET');
    app()->instance('request', $request);

    $content = Blade::render(
        <<<'BLADE'
<x-affiliates::public-referral-banner :show-checkout-link="true" />
BLADE,
        deleteCachedView: true,
    );

    expect($content)
        ->toContain('Referral Applied')
        ->toContain($this->affiliate->name)
        ->toContain($this->affiliate->default_voucher_code)
        ->toContain(url('/checkout?aff=' . $this->affiliate->code));
});
