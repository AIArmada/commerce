<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use OwenIt\Auditing\Contracts\Auditable;

it('tax class is auditable', function (): void {
    $traits = class_uses_recursive(TaxClass::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and(in_array(Auditable::class, class_implements(TaxClass::class), true))->toBeTrue();
});

it('tax rate is auditable', function (): void {
    $traits = class_uses_recursive(TaxRate::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and(in_array(Auditable::class, class_implements(TaxRate::class), true))->toBeTrue();
});

it('tax zone is auditable', function (): void {
    $traits = class_uses_recursive(TaxZone::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and(in_array(Auditable::class, class_implements(TaxZone::class), true))->toBeTrue();
});

it('tax exemption is auditable', function (): void {
    $traits = class_uses_recursive(TaxExemption::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and(in_array(Auditable::class, class_implements(TaxExemption::class), true))->toBeTrue();
});
