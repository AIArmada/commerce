<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use Illuminate\Database\Eloquent\Model;

it('returns instantiable model for unified subscriptions', function (): void {
    $modelClass = UnifiedSubscriptionResource::getModel();

    expect(class_exists($modelClass))->toBeTrue();

    $instance = new $modelClass;

    expect($instance)->toBeInstanceOf(Model::class);
});

it('returns instantiable model for unified invoices', function (): void {
    $modelClass = UnifiedInvoiceResource::getModel();

    expect(class_exists($modelClass))->toBeTrue();

    $instance = new $modelClass;

    expect($instance)->toBeInstanceOf(Model::class);
});
