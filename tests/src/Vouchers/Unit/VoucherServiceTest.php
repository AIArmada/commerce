<?php

declare(strict_types=1);

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherService;

test('voucher service can find voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'FINDME',
        'name' => 'Find Me',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $found = $service->find('findme'); // normalized

    expect($found)->toBeInstanceOf(AIArmada\Vouchers\Data\VoucherData::class)
        ->and($found->code)->toBe('FINDME');
});

test('voucher service find returns null for non-existent', function (): void {
    $service = app(VoucherService::class);

    $found = $service->find('nonexistent');

    expect($found)->toBeNull();
});

test('voucher service find or fail throws for non-existent', function (): void {
    $service = app(VoucherService::class);

    expect(fn () => $service->findOrFail('nonexistent'))->toThrow(AIArmada\Vouchers\Exceptions\VoucherNotFoundException::class);
});

test('voucher service find or fail returns voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'FINDFAIL',
        'name' => 'Find Fail',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $found = $service->findOrFail('findfail');

    expect($found)->toBeInstanceOf(AIArmada\Vouchers\Data\VoucherData::class)
        ->and($found->code)->toBe('FINDFAIL');
});

test('voucher service can create voucher', function (): void {
    $service = app(VoucherService::class);

    $data = [
        'code' => 'CREATE',
        'name' => 'Create Test',
        'type' => 'percentage',
        'value' => 15,
        'currency' => 'MYR',
    ];

    $created = $service->create($data);

    expect($created)->toBeInstanceOf(AIArmada\Vouchers\Data\VoucherData::class)
        ->and($created->code)->toBe('CREATE')
        ->and($created->name)->toBe('Create Test');
});

test('voucher service can update voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'UPDATE',
        'name' => 'Update Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $updated = $service->update('update', ['name' => 'Updated Name', 'code' => 'UPDATED']);

    expect($updated)->toBeInstanceOf(AIArmada\Vouchers\Data\VoucherData::class)
        ->and($updated->name)->toBe('Updated Name')
        ->and($updated->code)->toBe('UPDATED');
});

test('voucher service can delete voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'DELETE',
        'name' => 'Delete Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $deleted = $service->delete('delete');

    expect($deleted)->toBeTrue()
        ->and($service->find('delete'))->toBeNull();
});

test('voucher service delete returns false for non-existent', function (): void {
    $service = app(VoucherService::class);

    $deleted = $service->delete('nonexistent');

    expect($deleted)->toBeFalse();
});

test('voucher service is valid', function (): void {
    $voucher = Voucher::create([
        'code' => 'ISVALID',
        'name' => 'Is Valid',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    expect($service->isValid('isvalid'))->toBeTrue();
});

test('voucher service is valid returns false for invalid', function (): void {
    $service = app(VoucherService::class);

    expect($service->isValid('invalid'))->toBeFalse();
});

test('voucher service can be used by user', function (): void {
    $voucher = Voucher::create([
        'code' => 'CANUSE',
        'name' => 'Can Use',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit_per_user' => 2,
    ]);

    $user = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    $service = app(VoucherService::class);

    expect($service->canBeUsedBy('canuse', $user))->toBeTrue();
});

test('voucher service can be used by returns false when limit reached', function (): void {
    $voucher = Voucher::create([
        'code' => 'LIMITREACHED',
        'name' => 'Limit Reached',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit_per_user' => 1,
    ]);

    $user = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    // Add usage
    AIArmada\Vouchers\Models\VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 10,
        'currency' => 'MYR',
        'used_at' => now(),
        'redeemed_by_id' => 1,
        'redeemed_by_type' => 'User',
    ]);

    $service = app(VoucherService::class);

    expect($service->canBeUsedBy('limitreached', $user))->toBeFalse();
});

test('voucher service can be used by returns false for non-existent', function (): void {
    $user = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    $service = app(VoucherService::class);

    expect($service->canBeUsedBy('nonexistent', $user))->toBeFalse();
});

test('voucher service get remaining uses', function (): void {
    $voucher = Voucher::create([
        'code' => 'REMAIN',
        'name' => 'Remain',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 5,
    ]);

    $service = app(VoucherService::class);

    expect($service->getRemainingUses('remain'))->toBe(5);
});

test('voucher service get remaining uses returns 0 for non-existent', function (): void {
    $service = app(VoucherService::class);

    expect($service->getRemainingUses('nonexistent'))->toBe(0);
});

test('voucher service get usage history', function (): void {
    $voucher = Voucher::create([
        'code' => 'HISTORY',
        'name' => 'History',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $history = $service->getUsageHistory('history');

    expect($history)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
});

test('voucher service get usage history returns empty for non-existent', function (): void {
    $service = app(VoucherService::class);

    $history = $service->getUsageHistory('nonexistent');

    expect($history)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
        ->and($history->isEmpty())->toBeTrue();
});

test('voucher service normalize code with uppercase', function (): void {
    Illuminate\Support\Facades\Config::set('vouchers.code.auto_uppercase', true);

    $service = app(VoucherService::class);

    // Access private method via reflection
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('normalizeCode');
    $method->setAccessible(true);

    expect($method->invoke($service, ' lowercase '))->toBe('LOWERCASE');
});

test('voucher service normalize code without uppercase', function (): void {
    Illuminate\Support\Facades\Config::set('vouchers.code.auto_uppercase', false);

    $service = app(VoucherService::class);

    // Access private method via reflection
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('normalizeCode');
    $method->setAccessible(true);

    expect($method->invoke($service, ' lowercase '))->toBe('lowercase');
});
