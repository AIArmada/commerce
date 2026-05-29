<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Models\Segment;
use OwenIt\Auditing\Contracts\Auditable;

it('customer model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(Customer::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(Customer::class), true))->toBeTrue();
});

it('address model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(Address::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(Address::class), true))->toBeTrue();
});

it('customer group model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(CustomerGroup::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(CustomerGroup::class), true))->toBeTrue();
});

it('customer note model is activity loggable', function (): void {
    $traits = class_uses_recursive(CustomerNote::class);

    expect($traits)->toContain(LogsCommerceActivity::class);
});

it('segment model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(Segment::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(Segment::class), true))->toBeTrue();
});
