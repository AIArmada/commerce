<?php

declare(strict_types=1);

use AIArmada\Affiliates\Merchant\CaptureNetworkReferral;
use AIArmada\Affiliates\Merchant\NetworkPostbackClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function referralRequest(array $query): Request
{
    $request = Request::create('/products/x', 'GET', $query);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

describe('Merchant SDK', function (): void {
    test('referral middleware captures the link code into session', function (): void {
        $request = referralRequest(['anl' => 'link123']);

        app(CaptureNetworkReferral::class)->handle($request, static fn ($req) => response('ok'));

        expect($request->session()->get('affiliate_network.link_code'))->toBe('link123');
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

        Http::fake(['*' => Http::response(['ok' => true, 'duplicate' => false], 200)]);

        $result = app(NetworkPostbackClient::class)->report('link123', 'ORDER-7', 89900, 'MYR');

        expect($result['ok'])->toBeTrue()->and($result['status'])->toBe(200);

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://network.example/api/affiliate-network/conversions'
                && $request['site'] === 'merchant.example'
                && $request['link_code'] === 'link123'
                && $request['external_reference'] === 'ORDER-7'
                && $request['revenue_minor'] === 89900
                && $request->hasHeader('Authorization', 'Bearer site-secret');
        });
    });

    test('postback client sends zero-revenue reports and honors a custom prefix', function (): void {
        config()->set('affiliates.merchant.network_url', 'https://network.example');
        config()->set('affiliates.merchant.prefix', 'custom/postbacks');
        config()->set('affiliates.merchant.site', 'merchant.example');
        config()->set('affiliates.merchant.token', 'site-secret');

        Http::fake(['*' => Http::response(['ok' => true, 'duplicate' => false], 200)]);

        $result = app(NetworkPostbackClient::class)->report('link123', 'ORDER-0', 0);

        expect($result['ok'])->toBeTrue();

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://network.example/custom/postbacks/conversions'
                && $request['revenue_minor'] === 0;
        });
    });

    test('postback client fails closed when unconfigured', function (): void {
        config()->set('affiliates.merchant.network_url', null);
        config()->set('affiliates.merchant.site', null);
        config()->set('affiliates.merchant.token', null);

        $result = app(NetworkPostbackClient::class)->report('link123', 'ORDER-7', 100);

        expect($result['ok'])->toBeFalse()->and($result['error'])->not->toBeEmpty();
    });
});
