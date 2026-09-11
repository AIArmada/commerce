<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentJnt\Actions\SyncTrackingAction;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use Illuminate\Support\Facades\Http;

uses(FilamentJntTestCase::class);

it('debounces repeated SyncTrackingAction carrier polling within the configured window', function (): void {
    Http::fake([
        '*/api/logistics/trace' => Http::response([
            'code' => '1',
            'msg' => 'Success',
            'data' => [
                'billCode' => 'TRK-SYNC-POLLING',
                'txlogisticId' => 'ORDER-SYNC-POLLING',
                'details' => [],
            ],
        ]),
    ]);

    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Polling User',
        'email' => 'sync-polling@example.test',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($user);

    app()->instance(
        JntTrackingService::class,
        new JntTrackingService(
            new JntExpressService(
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
            ),
            new JntStatusMapper,
        ),
    );

    $order = JntOrder::query()->create([
        'order_id' => 'ORDER-SYNC-POLLING',
        'customer_code' => 'TESTCUST',
        'tracking_number' => 'TRK-SYNC-POLLING',
    ]);

    $handler = SyncTrackingAction::make()->record($order)->getActionFunction();

    $handler($order);
    $handler($order);

    Http::assertSentCount(1);
});
