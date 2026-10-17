<?php

declare(strict_types=1);

use AIArmada\Affiliates\Merchant\CaptureNetworkReferral;
use AIArmada\Affiliates\Merchant\NetworkPostbackClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function referralRequest(array $query): Request
{
    $request = Request::create('/products/x', 'GET', $query);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

describe('Merchant SDK', function (): void {
    test('referral middleware captures the link code and server-observed click time into session', function (): void {
        $request = referralRequest(['anl' => 'link123']);

        app(CaptureNetworkReferral::class)->handle($request, static fn ($req) => response('ok'));

        $clickedAt = $request->session()->get('affiliate_network.link_code_clicked_at');

        expect($request->session()->get('affiliate_network.link_code'))->toBe('link123')
            ->and($clickedAt)->toBeString()
            ->and(CarbonImmutable::parse($clickedAt)->diffInSeconds(CarbonImmutable::now()))->toBeLessThan(2);
    });

    test('referral middleware ignores requests without a code', function (): void {
        $request = referralRequest([]);

        app(CaptureNetworkReferral::class)->handle($request, static fn ($req) => response('ok'));

        expect($request->session()->has('affiliate_network.link_code'))->toBeFalse();
    });

    test('postback client reports paid orders to the network', function (): void {
        config()->set('affiliates.merchant.network_url', 'https://network.example');
        config()->set('affiliates.merchant.site', 'merchant.example');
        config()->set('affiliates.merchant.token', 'site-secret');

        Http::fake(['*' => Http::response([
            'ok' => true,
            'duplicate' => false,
            'decision' => ['winner' => 'network', 'reason' => 'last_touch'],
        ], 200)]);

        $networkClickedAt = CarbonImmutable::parse('2026-10-17T01:00:00+00:00');
        $engineTouchAt = CarbonImmutable::parse('2026-10-17T00:59:00+00:00');
        $result = app(NetworkPostbackClient::class)->report(
            'link123',
            'ORDER-7',
            89900,
            $networkClickedAt,
            $engineTouchAt,
            'MYR',
        );

        expect($result['ok'])->toBeTrue()
            ->and($result['status'])->toBe(200)
            ->and($result['decision'])->toBe(['winner' => 'network', 'reason' => 'last_touch']);

        Http::assertSent(static function ($request) use ($engineTouchAt, $networkClickedAt): bool {
            return $request->url() === 'https://network.example/api/affiliate-network/conversions'
                && $request['site'] === 'merchant.example'
                && $request['link_code'] === 'link123'
                && $request['external_reference'] === 'ORDER-7'
                && $request['revenue_minor'] === 89900
                && $request['engine_touch_at'] === $engineTouchAt->toIso8601String()
                && $request['network_clicked_at'] === $networkClickedAt->toIso8601String()
                && $request->hasHeader('Authorization', 'Bearer site-secret');
        });
    });

    test('postback client sends zero-revenue reports and honors a custom prefix', function (): void {
        config()->set('affiliates.merchant.network_url', 'https://network.example');
        config()->set('affiliates.merchant.prefix', 'custom/postbacks');
        config()->set('affiliates.merchant.site', 'merchant.example');
        config()->set('affiliates.merchant.token', 'site-secret');

        Http::fake(['*' => Http::response(['ok' => true, 'duplicate' => false], 200)]);

        $result = app(NetworkPostbackClient::class)->report(
            'link123',
            'ORDER-0',
            0,
            CarbonImmutable::parse('2026-10-17T01:00:00+00:00'),
        );

        expect($result['ok'])->toBeTrue();

        Http::assertSent(static function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://network.example/custom/postbacks/conversions'
                && $request['revenue_minor'] === 0
                && array_key_exists('engine_touch_at', $data)
                && $data['engine_touch_at'] === null;
        });
    });

    test('postback client fails closed when unconfigured', function (): void {
        config()->set('affiliates.merchant.network_url', null);
        config()->set('affiliates.merchant.site', null);
        config()->set('affiliates.merchant.token', null);

        $result = app(NetworkPostbackClient::class)->report(
            'link123',
            'ORDER-7',
            100,
            CarbonImmutable::now(),
        );

        expect($result['ok'])->toBeFalse()->and($result['error'])->not->toBeEmpty();
    });

    test('postback client voids reported conversions on refund', function (): void {
        config()->set('affiliates.merchant.network_url', 'https://network.example');
        config()->set('affiliates.merchant.site', 'merchant.example');
        config()->set('affiliates.merchant.token', 'site-secret');

        Http::fake(['*' => Http::response([
            'ok' => true,
            'duplicate' => false,
            'leg_status' => 'reversed',
        ], 200)]);

        $result = app(NetworkPostbackClient::class)->voidConversion('link123', 'ORDER-7', 'customer_refund');

        expect($result['ok'])->toBeTrue()
            ->and($result['status'])->toBe(200)
            ->and($result['leg_status'])->toBe('reversed');

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://network.example/api/affiliate-network/conversions/void'
                && $request['site'] === 'merchant.example'
                && $request['link_code'] === 'link123'
                && $request['external_reference'] === 'ORDER-7'
                && $request['reason'] === 'customer_refund'
                && $request->hasHeader('Authorization', 'Bearer ' . 'site' . '-secret');
        });
    });

    test('postback client void fails closed when unconfigured', function (): void {
        config()->set('affiliates.merchant.network_url', null);
        config()->set('affiliates.merchant.site', null);
        config()->set('affiliates.merchant.token', null);

        $result = app(NetworkPostbackClient::class)->voidConversion('link123', 'ORDER-7');

        expect($result['ok'])->toBeFalse()->and($result['error'])->not->toBeEmpty();
    });
});
