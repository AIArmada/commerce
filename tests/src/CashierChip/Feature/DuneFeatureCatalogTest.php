<?php

declare(strict_types=1);

use AIArmada\CashierChip\Features\Subscriptions\Actions\RenewSubscriptions;
use AIArmada\CashierChip\Platform\FeatureCatalog;

it('registers the Subscriptions feature with its declared resource ownership', function (): void {
    $root = dirname(__DIR__, 4) . '/packages/cashier-chip';
    $catalog = FeatureCatalog::scan($root, 'AIArmada\\CashierChip');

    expect($catalog['features'])->toHaveKey('Subscriptions')
        ->and($catalog['owners']['table:cashier_chip_renewal_attempts'])->toBe('Subscriptions')
        ->and($catalog['writes'][RenewSubscriptions::class])->toContain('table:cashier_chip_renewal_attempts');
});
