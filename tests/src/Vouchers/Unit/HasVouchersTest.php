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
});
