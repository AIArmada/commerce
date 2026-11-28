<?php

declare(strict_types=1);

use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('jnt.owner.enabled', false);
});

it('refuses cancellation when an authoritative timestamp marks the order delivered', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-CANCEL-DELIVERED',
        'tracking_number' => 'TRK-CANCEL-DELIVERED',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
        'delivered_at' => CarbonImmutable::now(),
    ]);

    $service = Mockery::mock(JntExpressService::class);
    $service->shouldNotReceive('cancelOrder');
    app()->instance(JntExpressService::class, $service);

    expect(fn (): mixed => app(CancelStoredOrder::class)->execute((string) $order->id, 'Customer request'))
        ->toThrow(DomainException::class);

    expect($order->refresh()->status)->toBe('in_transit')
        ->and($order->cancelled_at)->toBeNull();
});

it('does not override tracking that reaches a terminal state during the carrier call', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-CANCEL-RACE',
        'tracking_number' => 'TRK-CANCEL-RACE',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $service = Mockery::mock(JntExpressService::class);
    $service->shouldReceive('cancelOrder')
        ->once()
        ->andReturnUsing(function () use ($order): array {
            DB::table($order->getTable())->where('id', $order->id)->update([
                'status' => 'delivered',
                'delivered_at' => CarbonImmutable::now(),
            ]);

            return ['code' => '1'];
        });
    app()->instance(JntExpressService::class, $service);

    expect(fn (): mixed => app(CancelStoredOrder::class)->execute((string) $order->id, 'Customer request'))
        ->toThrow(DomainException::class);

    expect($order->refresh()->status)->toBe('delivered')
        ->and($order->cancelled_at)->toBeNull();
});

it('leaves the order untouched when the carrier outcome is unknown', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-CANCEL-UNKNOWN',
        'tracking_number' => 'TRK-CANCEL-UNKNOWN',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $service = Mockery::mock(JntExpressService::class);
    $service->shouldReceive('cancelOrder')->once()->andThrow(new JntNetworkException('Network timeout'));
    app()->instance(JntExpressService::class, $service);

    expect(fn (): mixed => app(CancelStoredOrder::class)->execute((string) $order->id, 'Customer request'))
        ->toThrow(JntNetworkException::class);

    expect($order->refresh()->status)->toBe('in_transit')
        ->and($order->cancelled_at)->toBeNull();
});

it('serializes duplicate cancellation attempts and calls the carrier once', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-CANCEL-DUPLICATE',
        'tracking_number' => 'TRK-CANCEL-DUPLICATE',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $service = Mockery::mock(JntExpressService::class);
    $service->shouldReceive('cancelOrder')->once()->andReturn(['code' => '1']);
    app()->instance(JntExpressService::class, $service);

    app(CancelStoredOrder::class)->execute((string) $order->id, 'Customer request');

    expect(fn (): mixed => app(CancelStoredOrder::class)->execute((string) $order->id, 'Customer request'))
        ->toThrow(DomainException::class);

    expect($order->refresh()->status)->toBe('cancelled');
});
