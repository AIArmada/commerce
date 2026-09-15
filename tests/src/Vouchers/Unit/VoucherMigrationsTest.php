<?php

declare(strict_types=1);

it('removes the unused voucher credit tables and redundant code index', function (): void {
    expect(Schema::hasTable('voucher_assignments'))->toBeFalse()
        ->and(Schema::hasTable('voucher_transactions'))->toBeFalse()
        ->and(Schema::hasIndex('vouchers', 'vouchers_code_index'))->toBeFalse()
        ->and(Schema::hasIndex('vouchers', 'vouchers_code_unique'))->toBeFalse()
        ->and(Schema::hasIndex('vouchers', 'vouchers_owner_code_unique'))->toBeTrue();
});

it('ships a single unique code index on vouchers', function (): void {
    $create = file_get_contents(dirname(__DIR__, 4) . '/packages/vouchers/database/migrations/2001_04_01_000001_create_vouchers_table.php');

    expect($create)->toBeString()
        ->and($create)->toContain("->unique(['owner_type', 'owner_id', 'code']")
        ->and($create)->toContain("'vouchers_owner_code_unique'")
        ->and($create)->not->toContain("->string('code')->unique()")
        ->and($create)->not->toContain("->index('code')");
});
