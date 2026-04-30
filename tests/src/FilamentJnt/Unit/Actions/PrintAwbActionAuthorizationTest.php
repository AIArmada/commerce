<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentJnt\Actions\BulkPrintAwbAction;
use AIArmada\FilamentJnt\Actions\PrintAwbAction;
use AIArmada\FilamentJnt\Actions\PrintAwbTableAction;
use AIArmada\Jnt\Models\JntOrder;

uses(FilamentJntTestCase::class);

it('blocks global orders for print actions unless global context is explicit', function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', true);

    $globalOrder = OwnerContext::withOwner(null, fn (): JntOrder => JntOrder::query()->create([
        'order_id' => 'ORD-PRINT-GLOBAL',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-PRINT-GLOBAL',
    ]));

    $actionClasses = [
        PrintAwbAction::class,
        PrintAwbTableAction::class,
        BulkPrintAwbAction::class,
    ];

    foreach ($actionClasses as $actionClass) {
        $method = new ReflectionMethod($actionClass, 'recordIsAccessible');

        $outsideGlobalContext = $method->invoke(null, $globalOrder);

        expect($outsideGlobalContext)
            ->toBeFalse("{$actionClass} should deny global rows when not in explicit global context.");

        $insideGlobalContext = OwnerContext::withOwner(null, fn (): mixed => $method->invoke(null, $globalOrder));

        expect($insideGlobalContext)
            ->toBeTrue("{$actionClass} should allow global rows only in explicit global context.");
    }
});
