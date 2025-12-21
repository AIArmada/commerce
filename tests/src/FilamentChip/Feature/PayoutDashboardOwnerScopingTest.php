<?php

declare(strict_types=1);

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Pages\PayoutDashboardPage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('does not leak payout metrics across owners', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
    });

    $ownerModel = new class extends Model
    {
        use HasUuids;

        protected $table = 'test_owners';

        public $timestamps = false;

        protected $guarded = [];

        public $incrementing = false;

        protected $keyType = 'string';
    };

    $ownerClass = $ownerModel::class;

    /** @var Model $ownerA */
    $ownerA = $ownerClass::query()->create();

    /** @var Model $ownerB */
    $ownerB = $ownerClass::query()->create();

    OwnerContext::override($ownerA);

    BankAccount::query()->create([
        'id' => 1,
        'account_number' => '1234567890',
        'bank_code' => 'MBBEMYKL',
        'name' => 'Owner A',
        'status' => 'active',
        'group_id' => null,
        'reference' => 'ref-a',
        'is_debiting_account' => false,
        'is_crediting_account' => true,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
        'rejection_reason' => null,
    ]);

    SendInstruction::query()->create([
        'id' => 101,
        'bank_account_id' => 1,
        'amount' => '10.00',
        'email' => 'a@example.com',
        'description' => 'Owner A payout',
        'reference' => 'pay-a',
        'state' => 'completed',
        'receipt_url' => null,
        'slug' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OwnerContext::override($ownerB);

    BankAccount::query()->create([
        'id' => 2,
        'account_number' => '9999999999',
        'bank_code' => 'HLBBMYKL',
        'name' => 'Owner B',
        'status' => 'active',
        'group_id' => null,
        'reference' => 'ref-b',
        'is_debiting_account' => false,
        'is_crediting_account' => true,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
        'rejection_reason' => null,
    ]);

    SendInstruction::query()->create([
        'id' => 202,
        'bank_account_id' => 2,
        'amount' => '999.00',
        'email' => 'b@example.com',
        'description' => 'Owner B payout',
        'reference' => 'pay-b',
        'state' => 'completed',
        'receipt_url' => null,
        'slug' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OwnerContext::override($ownerA);

    $page = new PayoutDashboardPage;
    $page->period = '30';
    $page->loadMetrics();

    expect($page->metrics['total_payouts'])->toBe(1);
    expect($page->metrics['pending_count'])->toBe(0);
    expect($page->metrics['failed_count'])->toBe(0);
    expect($page->metrics['active_accounts'])->toBe(1);
    expect($page->metrics['completed_amount'])->toBe(10.0);
});
