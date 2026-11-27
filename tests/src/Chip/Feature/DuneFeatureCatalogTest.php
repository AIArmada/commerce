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
