<?php

declare(strict_types=1);

it('does not hardcode JSON columns in vouchers fraud signals migration', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $path = $repoRoot . '/packages/vouchers/database/migrations/2024_01_01_000014_create_voucher_fraud_signals_table.php';

    expect($path)->toBeFile();

    $content = file_get_contents($path);

    expect($content)
        ->toBeString()
        ->not->toContain("->json('");
});
