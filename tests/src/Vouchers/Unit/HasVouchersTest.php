<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Support\CartWithVouchers;

it('retrieves applied vouchers from cart conditions', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'vouchers-test-user',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $voucherData = VoucherData::fromArray([
        'id' => 42,
        'code' => 'STACK10',
        'name' => 'Stackable Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 90, dynamic: false);
    $cart->addCondition($voucherCondition);

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->hasVoucher('STACK10'))->toBeTrue();

    $applied = $wrapper->getAppliedVouchers();

    expect($applied)->toHaveCount(1);

    /** @var VoucherCondition $appliedVoucher */
    $appliedVoucher = $applied[0];

    expect($appliedVoucher->getVoucherCode())->toBe('STACK10')
        ->and($wrapper->getAppliedVoucherCodes())->toBe(['STACK10']);

    // Test delegation to underlying cart
    expect($wrapper->getIdentifier())->toBe('vouchers-test-user');
});

it('collects voucher conditions from cart conditions', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'cart-condition-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $voucherData = VoucherData::fromArray([
        'id' => 43,
        'code' => 'CARTCOND',
        'name' => 'Cart Condition Test',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    // Add a CartCondition with type 'voucher'
    $cartCondition = new AIArmada\Cart\Conditions\CartCondition(
        name: 'voucher_cartcond',
        type: 'voucher',
        target: 'subtotal',
        value: '-25',
        attributes: [
            'voucher_id' => '43',
            'voucher_code' => 'CARTCOND',
            'voucher_type' => 'fixed',
            'description' => 'Cart Condition Test',
            'original_value' => 25,
            'voucher_data' => $voucherData->toArray(),
        ],
        order: 90
    );

    $cart->addCondition($cartCondition);

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->hasVoucher('CARTCOND'))->toBeTrue();

    $applied = $wrapper->getAppliedVouchers();

    expect($applied)->toHaveCount(1);
});

it('applies voucher successfully', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'apply-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    // Create a voucher
    $voucher = AIArmada\Vouchers\Models\Voucher::create([
        'code' => 'APPLYTEST',
        'name' => 'Apply Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $result = $wrapper->applyVoucher('applytest');

    expect($result)->toBe($wrapper)
        ->and($wrapper->hasVoucher('APPLYTEST'))->toBeTrue();
});

it('fails to apply invalid voucher code', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'invalid-apply',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    expect(fn () => $wrapper->applyVoucher('INVALID'))->toThrow(AIArmada\Vouchers\Exceptions\InvalidVoucherException::class);
});

it('removes voucher successfully', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'remove-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    // Add a voucher first
    $voucher = AIArmada\Vouchers\Models\Voucher::create([
        'code' => 'REMOVETEST',
        'name' => 'Remove Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $wrapper->applyVoucher('removetest');

    expect($wrapper->hasVoucher('REMOVETEST'))->toBeTrue();

    $result = $wrapper->removeVoucher('removetest');

    expect($result)->toBe($wrapper)
        ->and($wrapper->hasVoucher('REMOVETEST'))->toBeFalse();
});

it('removes non-existent voucher gracefully', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'remove-nonexist',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    $result = $wrapper->removeVoucher('NONEXISTENT');

    expect($result)->toBe($wrapper);
});

it('calculates voucher discount', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'discount-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    // Add a voucher
    $voucher = AIArmada\Vouchers\Models\Voucher::create([
        'code' => 'DISCOUNTTEST',
        'name' => 'Discount Test',
        'type' => 'fixed',
        'value' => 50,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    // Add an item to the cart
    $cart->add([
        'id' => 'item1',
        'name' => 'Test Item',
        'price' => 100,
        'quantity' => 1,
        'attributes' => [],
    ]);

    $wrapper->applyVoucher('discounttest');

    $discount = $wrapper->getVoucherDiscount();

    expect($discount)->toBe(50.0);
});

it('checks if can add voucher', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'can-add-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->canAddVoucher())->toBeTrue();

    // Test with max vouchers disabled
    Illuminate\Support\Facades\Config::set('vouchers.cart.max_vouchers_per_cart', 0);
    expect($wrapper->canAddVoucher())->toBeFalse();
    Illuminate\Support\Facades\Config::set('vouchers.cart.max_vouchers_per_cart', 1);
});

it('validates applied vouchers', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'validate-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    // Add a voucher
    $voucher = AIArmada\Vouchers\Models\Voucher::create([
        'code' => 'INVALIDATE',
        'name' => 'Invalidate Test',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $wrapper->applyVoucher('invalidate');

    expect($wrapper->hasVoucher('INVALIDATE'))->toBeTrue();

    // Make voucher invalid by changing status
    $voucher->update(['status' => 'expired']);

    // Validate - should remove because voucher is expired
    $removed = $wrapper->validateAppliedVouchers();

    expect($removed)->toBe(['INVALIDATE'])
        ->and($wrapper->hasVoucher('INVALIDATE'))->toBeFalse();
});

it('calculates voucher discount with stacking', function (): void {
    Illuminate\Support\Facades\Config::set('vouchers.cart.allow_stacking', true);
    Illuminate\Support\Facades\Config::set('vouchers.cart.max_vouchers_per_cart', 2);

    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'stacking-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    // Add an item
    $cart->add([
        'id' => 'item1',
        'name' => 'Test Item',
        'price' => 200,
        'quantity' => 1,
        'attributes' => [],
    ]);

    // Add two vouchers
    $voucher1 = AIArmada\Vouchers\Models\Voucher::create([
        'code' => 'STACK1',
        'name' => 'Stack 1',
        'type' => 'fixed',
        'value' => 20,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $voucher2 = AIArmada\Vouchers\Models\Voucher::create([
        'code' => 'STACK2',
        'name' => 'Stack 2',
        'type' => 'fixed',
        'value' => 30,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $wrapper->applyVoucher('stack1');
    $wrapper->applyVoucher('stack2');

    $discount = $wrapper->getVoucherDiscount();

    expect($discount)->toBe(50.0); // 20 + 30
});

it('tests cart with vouchers get underlying cart', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'underlying-test',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->getCart())->toBe($cart);
});

it('removes static voucher condition', function (): void {
    $storage = new class implements StorageInterface
    {
        private array $items = [];

        private array $conditions = [];

        private array $metadata = [];

        private array $ids = [];

        private array $versions = [];

        public function has(string $identifier, string $instance): bool
        {
            return isset($this->items[$identifier][$instance]) || isset($this->conditions[$identifier][$instance]);
        }

        public function forget(string $identifier, string $instance): void
        {
            unset($this->items[$identifier][$instance], $this->conditions[$identifier][$instance], $this->metadata[$identifier][$instance]);
        }

        public function flush(): void
        {
            $this->items = $this->conditions = $this->metadata = $this->ids = $this->versions = [];
        }

        public function getInstances(string $identifier): array
        {
            return array_keys($this->items[$identifier] ?? []);
        }

        public function forgetIdentifier(string $identifier): void
        {
            unset($this->items[$identifier], $this->conditions[$identifier], $this->metadata[$identifier], $this->ids[$identifier], $this->versions[$identifier]);
        }

        public function getItems(string $identifier, string $instance): array
        {
            return $this->items[$identifier][$instance] ?? [];
        }

        public function getConditions(string $identifier, string $instance): array
        {
            return $this->conditions[$identifier][$instance] ?? [];
        }

        public function putItems(string $identifier, string $instance, array $items): void
        {
            $this->items[$identifier][$instance] = $items;
        }

        public function putConditions(string $identifier, string $instance, array $conditions): void
        {
            $this->conditions[$identifier][$instance] = $conditions;
        }

        public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
        {
            $this->putItems($identifier, $instance, $items);
            $this->putConditions($identifier, $instance, $conditions);
        }

        public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
        {
            $this->metadata[$identifier][$instance][$key] = $value;
        }

        public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
        {
            $this->metadata[$identifier][$instance] = array_merge(
                $this->metadata[$identifier][$instance] ?? [],
                $metadata
            );
        }

        public function getMetadata(string $identifier, string $instance, string $key): mixed
        {
            return $this->metadata[$identifier][$instance][$key] ?? null;
        }

        public function clearMetadata(string $identifier, string $instance): void
        {
            unset($this->metadata[$identifier][$instance]);
        }

        public function clearAll(string $identifier, string $instance): void
        {
            $this->items[$identifier][$instance] = [];
            $this->conditions[$identifier][$instance] = [];
            unset($this->metadata[$identifier][$instance]);
        }

        public function getVersion(string $identifier, string $instance): ?int
        {
            return $this->versions[$identifier][$instance] ?? null;
        }

        public function getId(string $identifier, string $instance): ?string
        {
            return $this->ids[$identifier][$instance] ?? null;
        }

        public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
        {
            if (! $this->has($oldIdentifier, $instance) || $this->has($newIdentifier, $instance)) {
                return false;
            }

            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance] ?? [];
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance] ?? [];
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance] ?? [];
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance] ?? null;
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance] ?? null;

            $this->forget($oldIdentifier, $instance);

            return true;
        }

        public function getAllMetadata(string $identifier, string $instance): array
        {
            return $this->metadata[$identifier][$instance] ?? [];
        }

        public function getCreatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }

        public function getUpdatedAt(string $identifier, string $instance): ?string
        {
            return null;
        }
    };

    $cart = new Cart(
        storage: $storage,
        identifier: 'static-remove',
        events: null,
        instanceName: 'default',
        eventsEnabled: false,
        conditionResolver: new CartConditionResolver()
    );

    $voucherData = VoucherData::fromArray([
        'id' => 44,
        'code' => 'STATICREMOVE',
        'name' => 'Static Remove',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 90, dynamic: false);
    $cart->addCondition($voucherCondition);

    $wrapper = new CartWithVouchers($cart);

    expect($wrapper->hasVoucher('STATICREMOVE'))->toBeTrue();

    $result = $wrapper->removeVoucher('staticremove');

    expect($result)->toBe($wrapper)
        ->and($wrapper->hasVoucher('STATICREMOVE'))->toBeFalse();
});
