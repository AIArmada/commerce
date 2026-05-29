<?php

declare(strict_types=1);

use AIArmada\Chip\Models\ChipIntegerModel;
use AIArmada\Chip\Models\ChipModel;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use OwenIt\Auditing\Contracts\Auditable;

it('chip base model is auditable and activity loggable', function (): void {
    $traits = class_uses_recursive(ChipModel::class);

    expect($traits)->toContain(HasCommerceAudit::class)
        ->and($traits)->toContain(LogsCommerceActivity::class)
        ->and(in_array(Auditable::class, class_implements(ChipModel::class), true))->toBeTrue();
});

it('chip integer base model is activity loggable without audit contract', function (): void {
    $traits = class_uses_recursive(ChipIntegerModel::class);

    expect($traits)->toContain(LogsCommerceActivity::class)
        ->and($traits)->not->toContain(HasCommerceAudit::class)
        ->and(in_array(Auditable::class, class_implements(ChipIntegerModel::class), true))->toBeFalse();
});
