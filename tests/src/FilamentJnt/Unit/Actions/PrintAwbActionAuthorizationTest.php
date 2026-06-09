<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentJnt\Actions\PrintAwbTableAction;
use AIArmada\Jnt\Models\JntOrder;
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

    app()->bind(JntExpressService::class, fn () => new class($url)
    {
        public function __construct(private string $url) {}

        /**
         * @return array<string, string>
         */
        public function printOrder(string $orderId, ?string $trackingNumber = null): array
        {
            return [
                'txlogisticId' => $orderId,
                'billCode' => $trackingNumber ?? 'TRK-AWB-1',
                'urlContent' => $this->url,
            ];
        }
    });

    /** @var Component&MockInterface $livewire */
    $livewire = Mockery::mock(Component::class);

    $expectedJs = 'window.open(' . json_encode($url) . ', "_blank")';

    $livewire->shouldReceive('js')->once()->with($expectedJs);

    $tableAction = PrintAwbTableAction::make()->record($order);
    $tableHandler = $tableAction->getActionFunction();
    $tableHandler($order, $livewire);

    $notifications = session()->get('filament.notifications', []);
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

    app()->bind(JntExpressService::class, fn () => new class
    {
        /**
         * @return array<string, string>
         */
        public function printOrder(string $orderId, ?string $trackingNumber = null): array
        {
            return [
                'txlogisticId' => $orderId,
                'billCode' => $trackingNumber ?? 'TRK-AWB-INVALID-1',
                'base64EncodeContent' => 'this-is-not-valid-base64!!',
            ];
        }
    });

    /** @var Component&MockInterface $livewire */
    $livewire = Mockery::mock(Component::class);
    $livewire->shouldNotReceive('js');

    $tableAction = PrintAwbTableAction::make()->record($order);
    $tableHandler = $tableAction->getActionFunction();
    $tableHandler($order, $livewire);

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->pluck('title'))->toContain('AWB Not Available');
});
