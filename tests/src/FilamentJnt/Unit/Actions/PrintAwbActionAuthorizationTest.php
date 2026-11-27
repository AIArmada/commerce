<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentJnt\Actions\PrintAwbTableAction;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill;
use AIArmada\Jnt\Services\JntExpressService;
use Livewire\Component;
use Mockery\MockInterface;

uses(FilamentJntTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('blocks global orders for print action unless global context is explicit', function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', true);

    $globalOrder = OwnerContext::withOwner(null, fn (): JntOrder => JntOrder::query()->create([
        'order_id' => 'ORD-PRINT-GLOBAL',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-PRINT-GLOBAL',
    ]));

    $method = new ReflectionMethod(PrintAwbTableAction::class, 'recordIsAccessible');

    $outsideGlobalContext = $method->invoke(null, $globalOrder);

    expect($outsideGlobalContext)
        ->toBeFalse('PrintAwbTableAction should deny global rows when not in explicit global context.');

    $insideGlobalContext = OwnerContext::withOwner(null, fn (): mixed => $method->invoke(null, $globalOrder));

    expect($insideGlobalContext)
        ->toBeTrue('PrintAwbTableAction should allow global rows only in explicit global context.');
});

it('uses safely encoded javascript when opening AWB urls', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Printer User',
        'email' => 'print-awb@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-AWB-1',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-AWB-1',
    ]);

    $url = "https://example.test/awb?token=o'hara";

    $carrier = Mockery::mock(JntExpressService::class);
    $carrier->shouldReceive('printOrder')->once()->with('ORD-AWB-1', 'TRK-AWB-1')->andReturn([
        'txlogisticId' => 'ORD-AWB-1',
        'billCode' => 'TRK-AWB-1',
        'urlContent' => $url,
    ]);
    app()->instance(JntExpressService::class, $carrier);

    /** @var Component&MockInterface $livewire */
    $livewire = Mockery::mock(Component::class);

    $expectedJs = 'window.open(' . json_encode($url) . ', "_blank", "noopener,noreferrer")';

    $livewire->shouldReceive('js')->once()->with($expectedJs);

    $tableAction = PrintAwbTableAction::make()->record($order);
    $tableHandler = $tableAction->getActionFunction();
    $tableHandler($order, $livewire);

    $notifications = sent_filament_notifications();
    expect(collect($notifications)->pluck('title'))->toContain('AWB Ready');
});

it('does not open a download when waybill base64 payload is invalid', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Invalid Base64 User',
        'email' => 'invalid-base64-awb@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-AWB-INVALID-1',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-AWB-INVALID-1',
    ]);

    $carrier = Mockery::mock(JntExpressService::class);
    $carrier->shouldReceive('printOrder')->once()->with('ORD-AWB-INVALID-1', 'TRK-AWB-INVALID-1')->andReturn([
        'txlogisticId' => 'ORD-AWB-INVALID-1',
        'billCode' => 'TRK-AWB-INVALID-1',
        'base64EncodeContent' => 'this-is-not-valid-base64!!',
    ]);
    app()->instance(JntExpressService::class, $carrier);

    /** @var Component&MockInterface $livewire */
    $livewire = Mockery::mock(Component::class);
    $livewire->shouldNotReceive('js');

    $tableAction = PrintAwbTableAction::make()->record($order);
    $tableHandler = $tableAction->getActionFunction();
    $tableHandler($order, $livewire);

    $notifications = sent_filament_notifications();
    expect(collect($notifications)->pluck('title'))->toContain('AWB Not Available');
});

it('rejects non-http waybill URLs at the domain boundary', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-AWB-UNSAFE',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-AWB-UNSAFE',
    ]);
    $carrier = Mockery::mock(JntExpressService::class);
    $carrier->shouldReceive('printOrder')->once()->andReturn([
        'txlogisticId' => 'ORD-AWB-UNSAFE',
        'urlContent' => 'ftp://example.test/label.pdf',
    ]);
    app()->instance(JntExpressService::class, $carrier);

    expect(app(PrintableWaybill::class)->url($order->id, 'test-user'))->toBeNull();
});

it('rejects waybills with mismatched order identities or unsafe carrier payloads', function (array $payload): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-AWB-BOUNDARY',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-AWB-BOUNDARY',
    ]);

    $carrier = Mockery::mock(JntExpressService::class);
    $carrier->shouldReceive('printOrder')->once()->andReturn($payload);
    app()->instance(JntExpressService::class, $carrier);

    expect(app(PrintableWaybill::class)->url($order->id, 'test-user'))->toBeNull();
})->with([
    'different order' => [['txlogisticId' => 'OTHER-ORDER', 'urlContent' => 'https://example.test/label.pdf']],
    'malformed URL' => [['urlContent' => ['https://example.test/label.pdf']]],
    'credentials in URL' => [['urlContent' => 'https://user:password@example.test/label.pdf']],
    'invalid inline PDF with URL' => [['base64EncodeContent' => 'invalid!', 'urlContent' => 'https://example.test/label.pdf']],
]);
