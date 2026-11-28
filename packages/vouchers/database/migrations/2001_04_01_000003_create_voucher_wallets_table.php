<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';

        $voucherTable = $tables['vouchers'] ?? $prefix . 'vouchers';

        Schema::create($tableName, function (Blueprint $table) use ($voucherTable): void {
            $jsonType = commerce_json_column_type('vouchers', 'jsonb');

            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id')->constrained($voucherTable)->cascadeOnDelete();
            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('holder');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('redeemed_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            // Indexes for common queries
            $table->index('voucher_id'); // For querying wallet entries by voucher
            // Note: nullableUuidMorphs('owner') already creates index on ['owner_type', 'owner_id']
            // Note: nullableUuidMorphs('holder') already creates index on ['holder_type', 'holder_id']
            $table->index('claimed_at'); // For sorting by claim date
            $table->index('redeemed_at'); // For sorting by redemption date
            $table->index(['voucher_id', 'claimed_at', 'redeemed_at'], 'voucher_wallets_available_idx');
        });

        // Partial unique indexes are supported by PostgreSQL and SQLite only.
        // On SQLite the one-active-entry rule is enforced application-side by
        // AddVoucherToWallet (voucher-row lock plus unique-violation rescue).
        if (in_array(ConnectionDriver::name(Schema::getConnection()), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX voucher_wallets_one_active_per_holder ON {$tableName} (voucher_id, holder_type, holder_id) WHERE redeemed_at IS NULL");
        }
    }
};

it('isolates claims of the same global voucher to each exact wallet owner', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $holder = scopeWalletUser('shared-wallet-holder');
    $ownerA = scopeWalletUser('shared-wallet-owner-a');
    $ownerB = scopeWalletUser('shared-wallet-owner-b');
    $global = OwnerContext::withOwner(null, static fn (): Voucher => scopeVoucher(['code' => 'GLOBAL-WALLET-ISOLATION']));

    $globalEntry = OwnerContext::withOwner(null, fn () => $holder->addVoucherToWallet($global->code));

    $entryA = OwnerContext::withOwner($ownerA, function () use ($holder, $global, $ownerA) {
        expect($holder->hasVoucherInWallet($global->code))->toBeFalse()
            ->and($holder->removeVoucherFromWallet($global->code))->toBeFalse();

        $entry = $holder->addVoucherToWallet($global->code);

        expect((string) $entry->owner_id)->toBe((string) $ownerA->getKey())
            ->and($holder->addVoucherToWallet($global->code)->id)->toBe($entry->id);

        return $entry;
    });

    $entryB = OwnerContext::withOwner($ownerB, fn () => $holder->addVoucherToWallet($global->code));

    expect($entryA->id)->not->toBe($entryB->id)
        ->and($entryA->id)->not->toBe($globalEntry->id);

    OwnerContext::withOwner($ownerA, function () use ($holder, $global): void {
        expect($holder->removeVoucherFromWallet($global->code))->toBeTrue()
            ->and($holder->hasVoucherInWallet($global->code))->toBeFalse();
    });

    OwnerContext::withOwner($ownerB, fn () => expect($holder->hasVoucherInWallet($global->code))->toBeTrue());
    OwnerContext::withOwner(null, fn () => expect($holder->hasVoucherInWallet($global->code))->toBeTrue());
});

it('enforces global voucher code uniqueness in the database', function (): void {
    config()->set('vouchers.owner.enabled', true);

    OwnerContext::withOwner(null, function (): void {
        scopeVoucher(['code' => 'UNIQUE-GLOBAL-CODE']);

        expect(fn () => scopeVoucher(['code' => 'UNIQUE-GLOBAL-CODE']))->toThrow(QueryException::class);
    });
});
