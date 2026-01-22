<?php

declare(strict_types=1);

it('cashier stripe subscriptions use uuid user_id', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $migration = $repoRoot . '/packages/cashier/database/migrations/0002_create_subscriptions_table.php';

    $contents = file_get_contents($migration);

    expect($contents)->toBeString();
    expect($contents)->toContain("foreignUuid('user_id')");
    expect($contents)->not->toContain("foreignId('user_id')");
});

it('cashier stripe customer columns include default payment method', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $migration = $repoRoot . '/packages/cashier/database/migrations/0001_create_customer_columns.php';

    $contents = file_get_contents($migration);

    expect($contents)->toBeString();
    expect($contents)->toContain('stripe_default_payment_method');
});
