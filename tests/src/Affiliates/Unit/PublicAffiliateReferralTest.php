<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\ResolvePublicAffiliateReferralContext;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionActivity;
use AIArmada\Affiliates\Actions\Affiliates\TrackAffiliateVisit;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\Support\IpHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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
        encrypt('public-cookie-value'),
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

test('tracked visit stores a single IP hash and never the raw address', function (): void {
    $rawIp = '203.0.113.7';

    $this->call('GET', '/r/' . $this->affiliate->code, [], [], [], ['REMOTE_ADDR' => $rawIp])
        ->assertRedirect();

    $attribution = AffiliateAttribution::query()->sole();
    $expected = IpHasher::hash($rawIp);

    expect($expected)->toMatch('/^[0-9a-f]{64}$/')
        ->and($attribution->ip_address)->toBe($expected)
        ->and($attribution->ip_address)->not->toBe($rawIp)
        ->and($attribution->ip_address)->not->toBe(IpHasher::hash((string) $expected))
        ->and((string) json_encode($attribution->metadata ?? []))->not->toContain($rawIp);
});

test('path referral redirect preserves its emitted cookie instead of the tracker overwriting it', function (): void {
    $cookieName = (string) config('affiliates.cookies.name', 'affiliate_session');

    $previous = Affiliate::create([
        'code' => 'PREVIOUS-AFF-' . uniqid(),
        'name' => 'Previous Partner',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
        'default_voucher_code' => 'REFPREV' . uniqid(),
    ]);

    app(TrackAffiliateVisit::class)->handle(
        $previous->code,
        ['user_agent' => 'previous-agent', 'ip_address' => '203.0.113.5'],
        'previous-cookie',
    );

    $response = $this
        ->withUnencryptedCookie($cookieName, 'previous-cookie')
        ->withHeaders(['User-Agent' => 'new-agent'])
        ->get('/r/' . $this->affiliate->code);

    $response->assertRedirect();

    $emitted = $response->getCookie($cookieName, false);
    $attribution = AffiliateAttribution::query()
        ->where('affiliate_code', $this->affiliate->code)
        ->sole();

    expect($emitted)->not->toBeNull()
        ->and($attribution->cookie_value)->not->toBe('previous-cookie')
        ->and($emitted->getValue())->toBe($attribution->cookie_value);
});

test('touching attribution preserves the stored hash unless a new raw IP arrives', function (): void {
    $firstIp = '198.51.100.11';
    $secondIp = '198.51.100.22';

    app(TrackAffiliateVisit::class)->handle($this->affiliate->code, ['ip_address' => $firstIp], 'touch-cookie-f03');

    $stored = AffiliateAttribution::query()->where('cookie_value', 'touch-cookie-f03')->sole();

    expect($stored->ip_address)->toBe(IpHasher::hash($firstIp));

    app(AttributionActivity::class)->touch('touch-cookie-f03', []);

    expect($stored->refresh()->ip_address)->toBe(IpHasher::hash($firstIp));

    app(AttributionActivity::class)->touch('touch-cookie-f03', ['ip_address' => $secondIp]);

    $touched = $stored->refresh();

    expect($touched->ip_address)->toBe(IpHasher::hash($secondIp))
        ->and($touched->ip_address)->not->toBe(IpHasher::hash((string) IpHasher::hash($secondIp)));
});

test('attribution activity does not revive expired cookies', function (): void {
    app(TrackAffiliateVisit::class)->handle($this->affiliate->code, [], 'expired-touch-cookie');
    $attribution = AffiliateAttribution::query()->where('cookie_value', 'expired-touch-cookie')->sole();
    $attribution->update(['expires_at' => now()->subDay()]);
    $expiresAt = $attribution->fresh()->expires_at;

    expect(app(AttributionActivity::class)->touch('expired-touch-cookie'))->toBeNull()
        ->and($attribution->fresh()->expires_at->equalTo($expiresAt))->toBeTrue();
});
