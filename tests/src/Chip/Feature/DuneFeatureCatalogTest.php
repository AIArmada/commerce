<?php

declare(strict_types=1);

use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction;
use AIArmada\Chip\Platform\FeatureCatalog;

it('registers the Payouts feature with its declared resource ownership', function (): void {
    $root = dirname(__DIR__, 4) . '/packages/chip';
    $catalog = FeatureCatalog::scan($root, 'AIArmada\\Chip');

    expect($catalog['features'])->toHaveKey('Payouts')
        ->and($catalog['owners']['table:chip_send_instructions'])->toBe('Payouts')
        ->and($catalog['writes'][CreateSendInstruction::class])->toContain('table:chip_send_instructions');
});

it('declares Collect as the owner of mirrored CHIP records', function (): void {
    $catalog = FeatureCatalog::scan(dirname(__DIR__, 4) . '/packages/chip', 'AIArmada\\Chip');

    expect($catalog['owners']['table:chip_purchases'])->toBe('Collect')
        ->and($catalog['owners']['table:chip_clients'])->toBe('Collect')
        ->and($catalog['owners']['table:chip_payments'])->toBe('Collect');
});

it('declares the purchase cache as a Collect-owned store', function (): void {
    $catalog = FeatureCatalog::scan(dirname(__DIR__, 4) . '/packages/chip', 'AIArmada\\Chip');

    expect($catalog['owners']['store:chip.purchase_cache'])->toBe('Collect');
});
