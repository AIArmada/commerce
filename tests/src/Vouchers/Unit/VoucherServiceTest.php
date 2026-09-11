<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Support\VoucherLookupCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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

    expect($found)->toBeInstanceOf(VoucherData::class)
        ->and($found->code)->toBe('FINDME');
});

test('voucher service find returns null for non-existent', function (): void {
    $service = app(VoucherService::class);

    $found = $service->find('nonexistent');

    expect($found)->toBeNull();
});

test('voucher lookup can be refreshed after an out-of-band provider update', function (): void {
    $voucher = Voucher::create([
        'code' => 'CACHEME',
        'name' => 'Before update',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    expect($service->find('cacheme')?->name)->toBe('Before update');

    DB::table($voucher->getTable())
        ->where('id', $voucher->getKey())
        ->update(['name' => 'After update']);

    expect($service->find('cacheme')?->name)->toBe('Before update');

    $service->invalidate('cacheme');

    expect($service->find('cacheme')?->name)->toBe('After update');
});

test('voucher lookup cache keeps owner and global scopes separate', function (): void {
    $cache = app(VoucherLookupCache::class);
    $ownerA = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'test-owner';
        }

        public function getKey(): string
        {
            return 'owner-a';
        }
    };
    $ownerB = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'test-owner';
        }

        public function getKey(): string
        {
            return 'owner-b';
        }
    };
    $ownerVoucher = VoucherData::fromArray([
        'id' => 'owner-a-voucher',
        'code' => 'SHARED-CODE',
        'name' => 'Owner A',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);
    $otherOwnerVoucher = VoucherData::fromArray([
        'id' => 'owner-b-voucher',
        'code' => 'SHARED-CODE',
        'name' => 'Owner B',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);
    $globalVoucher = VoucherData::fromArray([
        'id' => 'global-voucher',
        'code' => 'SHARED-CODE',
        'name' => 'Global',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($cache->remember('SHARED-CODE', $ownerA, false, fn (): VoucherData => $ownerVoucher)?->name)
        ->toBe('Owner A')
        ->and($cache->remember('SHARED-CODE', $ownerB, false, fn (): VoucherData => $otherOwnerVoucher)?->name)
        ->toBe('Owner B')
        ->and($cache->remember('SHARED-CODE', $ownerA, false, fn (): VoucherData => $otherOwnerVoucher)?->name)
        ->toBe('Owner A')
        ->and($cache->remember('SHARED-CODE', null, false, fn (): VoucherData => $globalVoucher)?->name)
        ->toBe('Global');
});

test('voucher model writes invalidate cached lookups automatically', function (): void {
    $voucher = Voucher::create([
        'code' => 'AUTOCLEAR',
        'name' => 'Before model update',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    expect($service->find('autoclear')?->name)->toBe('Before model update');

    $voucher->update(['name' => 'After model update']);

    expect($service->find('autoclear')?->name)->toBe('After model update');
});

test('voucher code changes invalidate both old and new lookup keys', function (): void {
    $voucher = Voucher::create([
        'code' => 'OLDCODE',
        'name' => 'Code Change',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    expect($service->find('oldcode')?->name)->toBe('Code Change');

    $voucher->update(['code' => 'NEWCODE']);

    expect($service->find('oldcode'))->toBeNull()
        ->and($service->find('newcode')?->name)->toBe('Code Change');
});

test('voucher deletes invalidate cached lookups', function (): void {
    $voucher = Voucher::create([
        'code' => 'DELETECACHE',
        'name' => 'Delete Cache',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    expect($service->find('deletecache'))->not->toBeNull();

    $voucher->delete();

    expect($service->find('deletecache'))->toBeNull();
});

test('voucher creates invalidate a stale key left by an out-of-band delete', function (): void {
    $voucher = Voucher::create([
        'code' => 'RECREATECACHE',
        'name' => 'Before recreate',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    expect($service->find('recreatecache')?->name)->toBe('Before recreate');

    DB::table($voucher->getTable())
        ->where('id', $voucher->getKey())
        ->delete();

    Voucher::create([
        'code' => 'RECREATECACHE',
        'name' => 'After recreate',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($service->find('recreatecache')?->name)->toBe('After recreate');
});

test('voucher service find or fail throws for non-existent', function (): void {
    $service = app(VoucherService::class);

    expect(fn () => $service->findOrFail('nonexistent'))->toThrow(VoucherNotFoundException::class);
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

    expect($found)->toBeInstanceOf(VoucherData::class)
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

    expect($created)->toBeInstanceOf(VoucherData::class)
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

    expect($updated)->toBeInstanceOf(VoucherData::class)
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

test('voucher service redeem records fixed discount amount', function (): void {
    $voucher = Voucher::create([
        'code' => 'REDEEMFIXED',
        'name' => 'Redeem Fixed',
        'type' => 'fixed',
        'value' => 500,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $order = Order::factory()->create([
        'order_number' => 'ORD-REDEEM-123',
        'subtotal' => 10000,
        'discount_total' => 500,
        'grand_total' => 9500,
        'currency' => 'MYR',
    ]);

    $service->redeem('redeemfixed', (string) $order->id);

    $usage = VoucherUsage::where('voucher_id', $voucher->id)->first()?->load('redeemedBy');

    expect($usage)->not->toBeNull()
        ->and($usage?->discount_amount)->toBe(500)
        ->and($usage?->currency)->toBe('MYR')
        ->and($usage?->channel)->toBe('checkout')
        ->and($usage?->redeemed_by_id)->toBe((string) $order->id)
        ->and($usage?->metadata)->toMatchArray([
            'order_id' => (string) $order->id,
            'order_number' => 'ORD-REDEEM-123',
            'subtotal' => 10000,
            'discount_total' => 500,
            'grand_total' => 9500,
        ])
        ->and($usage?->redeemedBy?->is($order))->toBeTrue();
});

test('voucher service redeem records the allocated checkout discount and voucher currency', function (): void {
    $voucher = Voucher::create([
        'code' => 'REDEEMALLOCATED',
        'name' => 'Redeem Allocated',
        'type' => 'fixed',
        'value' => 500,
        'currency' => 'USD',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);
    $order = Order::factory()->create([
        'order_number' => 'ORD-REDEEM-ALLOCATED',
        'subtotal' => 10000,
        'discount_total' => 300,
        'grand_total' => 9700,
        'currency' => 'USD',
    ]);

    $service->redeem('redeemallocated', (string) $order->id, 300, 'USD');

    $usage = VoucherUsage::where('voucher_id', $voucher->id)->first();

    expect($usage)->not->toBeNull()
        ->and($usage?->discount_amount)->toBe(300)
        ->and($usage?->currency)->toBe('USD');
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

    expect($history)->toBeInstanceOf(Collection::class);
});

test('voucher service get usage history returns empty for non-existent', function (): void {
    $service = app(VoucherService::class);

    $history = $service->getUsageHistory('nonexistent');

    expect($history)->toBeInstanceOf(Collection::class)
        ->and($history->isEmpty())->toBeTrue();
});

test('voucher service normalize code with uppercase', function (): void {
    Config::set('vouchers.code.auto_uppercase', true);

    $service = app(VoucherService::class);

    // Access private method via reflection
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('normalizeCode');

    expect($method->invoke($service, ' lowercase '))->toBe('LOWERCASE');
});

test('voucher service normalize code without uppercase', function (): void {
    Config::set('vouchers.code.auto_uppercase', false);

    $service = app(VoucherService::class);

    // Access private method via reflection
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('normalizeCode');

    expect($method->invoke($service, ' lowercase '))->toBe('lowercase');
});

test('voucher service release clears all reservation cache keys for voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'RESERVECLR',
        'name' => 'Reservation Clear',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $service->reserve('RESERVECLR', 'session-a');
    $service->reserve('RESERVECLR', 'session-b');

    $reservationA = Cache::get("voucher_reservation:{$voucher->id}:session-a");
    $reservationB = Cache::get("voucher_reservation:{$voucher->id}:session-b");
    $sessionsIndex = Cache::get("voucher_reservation_sessions:{$voucher->id}");

    expect($reservationA)->toBeArray()
        ->and($reservationB)->toBeArray()
        ->and($sessionsIndex)->toContain('session-a', 'session-b');

    $service->release('RESERVECLR');

    expect(Cache::get("voucher_reservation:{$voucher->id}:session-a"))->toBeNull()
        ->and(Cache::get("voucher_reservation:{$voucher->id}:session-b"))->toBeNull()
        ->and(Cache::get("voucher_reservation_sessions:{$voucher->id}"))->toBeNull();
});

test('it releases only one session when session id is provided', function (): void {
    $voucher = Voucher::create([
        'code' => 'RELEASESNGL',
        'name' => 'Release Single Session',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $service = app(VoucherService::class);

    $service->reserve('RELEASESNGL', 'session-a');
    $service->reserve('RELEASESNGL', 'session-b');

    $service->release('RELEASESNGL', 'session-a');

    expect(Cache::get("voucher_reservation:{$voucher->id}:session-a"))->toBeNull()
        ->and(Cache::get("voucher_reservation:{$voucher->id}:session-b"))->toBeArray()
        ->and(Cache::get("voucher_reservation_sessions:{$voucher->id}"))->not->toContain('session-a')
        ->and(Cache::get("voucher_reservation_sessions:{$voucher->id}"))->toContain('session-b');
});

test('it creates only one usage on duplicate commit', function (): void {
    $voucher = Voucher::create([
        'code' => 'DUPREDEEM',
        'name' => 'Duplicate Redeem',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 1,
    ]);

    $service = app(VoucherService::class);

    $order = Order::factory()->create([
        'order_number' => 'ORD-DUP-123',
        'subtotal' => 10000,
        'discount_total' => 1000,
        'grand_total' => 9000,
        'currency' => 'MYR',
    ]);

    $service->redeem('dupredeem', (string) $order->id);

    expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(1);

    try {
        $service->redeem('dupredeem', (string) $order->id);
    } catch (VoucherUsageLimitException) {
        // ponytail: usage_limit blocks duplicate commit
    }

    expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(1);
});
