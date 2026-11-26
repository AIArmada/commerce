<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\NetworkPostbackGateway;
use AIArmada\Affiliates\Features\Merchant\EntryPoints\Middleware\CaptureNetworkReferral;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function merchantRequest(array $query): Request
{
    $request = Request::create('/checkout', 'GET', $query);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

function configureMerchant(): void
{
    config()->set('affiliates.merchant.network_url', 'https://network.example');
    config()->set('affiliates.merchant.site', 'merchant.example');
    config()->set('affiliates.merchant.token', 'site-secret');
    config()->set('affiliates.merchant.prefix', 'api/affiliate-network');
}

describe('NetworkPostbackGateway extended payloads', function (): void {
    test('reports coupon-only conversions with sub ids and an event type', function (): void {
        configureMerchant();
        Http::fake(['*' => Http::response(['ok' => true, 'duplicate' => false], 200)]);

        $result = app(NetworkPostbackGateway::class)->report(
            linkCode: '',
            externalReference: 'COUPON-ORDER-1',
            revenueMinor: 12500,
            networkClickedAt: CarbonImmutable::parse('2026-10-17T01:00:00+00:00'),
            currency: 'MYR',
            couponCode: 'SPRING10',
            eventType: 'subscription',
            subIds: ['sub1' => 'creator-a', 'sub2' => 'stories'],
        );

        expect($result->ok)->toBeTrue();

        Http::assertSent(static function ($request): bool {
            $data = $request->data();

            return $request['link_code'] === null
                && $request['coupon_code'] === 'SPRING10'
                && $request['event_type'] === 'subscription'
                && $request['sub_id'] === 'creator-a'
                && $request['sub_id_2'] === 'stories'
                && ! array_key_exists('sub_id_3', $data);
        });
    });

    test('adjusts a conversion with a new revenue value', function (): void {
        configureMerchant();
        Http::fake(['*' => Http::response(['ok' => true, 'leg_status' => 'posted'], 200)]);

        $result = app(NetworkPostbackGateway::class)->adjustConversion(
            linkCode: 'link123',
            externalReference: 'ORDER-9',
            adjustmentReference: 'ADJ-1',
            newRevenueMinor: 5000,
            reason: 'partial refund',
        );

        expect($result->ok)->toBeTrue()
            ->and($result->legStatus)->toBe('posted');

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://network.example/api/affiliate-network/conversions/adjust'
                && $request['adjustment_reference'] === 'ADJ-1'
                && $request['new_revenue_minor'] === 5000
                && $request['reason'] === 'partial refund'
                && ! array_key_exists('delta_revenue_minor', $request->data());
        });
    });

    test('voids a coupon-only conversion without a link code', function (): void {
        configureMerchant();
        Http::fake(['*' => Http::response(['ok' => true, 'duplicate' => false, 'leg_status' => 'reversed'], 200)]);

        $result = app(NetworkPostbackGateway::class)->voidConversion(
            linkCode: '',
            externalReference: 'COUPON-VOID-1',
            reason: 'refund',
            couponCode: 'SPRING10',
            eventType: 'subscription',
        );

        expect($result->ok)->toBeTrue();

        Http::assertSent(static function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://network.example/api/affiliate-network/conversions/void'
                && $request['coupon_code'] === 'SPRING10'
                && $request['event_type'] === 'subscription'
                && ! array_key_exists('link_code', $data);
        });
    });

    test('refuses to report when neither a link nor a coupon is supplied', function (): void {
        configureMerchant();

        $result = app(NetworkPostbackGateway::class)->report(
            linkCode: '',
            externalReference: 'NO-ATTRIBUTION',
            revenueMinor: 1000,
            networkClickedAt: CarbonImmutable::now(),
        );

        expect($result->ok)->toBeFalse()
            ->and($result->error)->toBe('no network attribution');
    });
});

describe('CaptureNetworkReferral sub id capture', function (): void {
    test('captures bounded sub ids into the session', function (): void {
        $request = merchantRequest(['anl' => 'link123', 'sub1' => 'alpha', 'sub2' => 'beta', 'sub3' => 'gamma']);

        app(CaptureNetworkReferral::class)->handle($request, static fn ($req) => response('ok'));

        expect($request->session()->get('affiliate_network.link_code_sub_ids'))->toBe([
            'sub1' => 'alpha',
            'sub2' => 'beta',
            'sub3' => 'gamma',
        ]);
    });

    test('drops sub ids that exceed the length bound', function (): void {
        $request = merchantRequest(['anl' => 'link123', 'sub1' => str_repeat('a', 256), 'sub2' => 'ok']);

        app(CaptureNetworkReferral::class)->handle($request, static fn ($req) => response('ok'));

        expect($request->session()->get('affiliate_network.link_code_sub_ids'))->toBe(['sub2' => 'ok']);
    });
    test('new referrals replace old sub ids and unrelated query parameters cannot overwrite attribution', function (): void {
        $request = merchantRequest(['anl' => 'link123', 'sub1' => 'old']);
        app(CaptureNetworkReferral::class)->handle($request, static fn ($req) => response('ok'));

        $next = merchantRequest(['sub1' => 'forged']);
        app(CaptureNetworkReferral::class)->handle($next, static fn ($req) => response('ok'));
        expect($next->session()->get('affiliate_network.link_code_sub_ids'))->toBe(['sub1' => 'old']);

        $fresh = merchantRequest(['anl' => 'link456']);
        app(CaptureNetworkReferral::class)->handle($fresh, static fn ($req) => response('ok'));
        expect($fresh->session()->get('affiliate_network.link_code'))->toBe('link456')
            ->and($fresh->session()->get('affiliate_network.link_code_sub_ids'))->toBe([]);
    });
});
