<?php

declare(strict_types=1);

use AIArmada\Jnt\Actions\Tracking\TrackParcel;
use AIArmada\Jnt\Services\JntExpressService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

function makeJntPollingTestService(): JntExpressService
{
    return new JntExpressService(
        customerCode: 'TESTCUST',
        password: 'test-password',
        config: [
            'environment' => 'testing',
            'api_account' => 'test-account',
            'private_key' => 'test-private-key',
            'base_urls' => [
                'testing' => 'https://jtjms-api.jtexpress.my',
                'production' => 'https://jtjms-api.jtexpress.my',
            ],
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
            ],
            'tracking' => [
                'polling_debounce_minutes' => 10,
            ],
            'logging' => [
                'enabled' => false,
            ],
        ],
    );
}

function fakeJntTrackingResponse(): array
{
    return [
        'code' => '1',
        'msg' => 'Success',
        'data' => [
            'billCode' => 'TRK-POLLING',
            'txlogisticId' => 'ORDER-POLLING',
            'details' => [],
        ],
    ];
}

it('debounces repeated TrackParcel polling within the configured window', function (): void {
    Http::fake([
        '*/api/logistics/trace' => Http::response(fakeJntTrackingResponse()),
    ]);

    $action = new TrackParcel(makeJntPollingTestService());

    $first = $action->handle(trackingNumber: 'TRK-POLLING');
    $second = $action->handle(trackingNumber: 'TRK-POLLING');

    expect($second->trackingNumber)->toBe($first->trackingNumber);
    Http::assertSentCount(1);
});

it('polls again after the debounce window expires', function (): void {
    Http::fake([
        '*/api/logistics/trace' => Http::response(fakeJntTrackingResponse()),
    ]);

    $now = CarbonImmutable::parse('2026-09-12 00:00:00');
    CarbonImmutable::setTestNow($now);

    try {
        $action = new TrackParcel(makeJntPollingTestService());

        $action->handle(trackingNumber: 'TRK-POLLING-EXPIRY');
        CarbonImmutable::setTestNow($now->addMinutes(11));
        $action->handle(trackingNumber: 'TRK-POLLING-EXPIRY');
    } finally {
        CarbonImmutable::setTestNow();
    }

    Http::assertSentCount(2);
});
