<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentSucceeded as CashierPaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentSucceeded as CashierChipPaymentSucceeded;
use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Contracts\ProvidesInventoryCommitContext;
use AIArmada\Inventory\Listeners\CommitInventoryOnPayment;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryAllocationService;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->level = InventoryLevel::factory()->create([
        'inventoryable_type' => $this->item->getMorphClass(),
        'inventoryable_id' => $this->item->getKey(),
        'location_id' => $this->location->id,
        'quantity_on_hand' => 100,
        'quantity_reserved' => 0,
    ]);
    $this->allocationService = app(InventoryAllocationService::class);
    $this->listener = new CommitInventoryOnPayment($this->allocationService);
});

function makeTypedPayment(array $metadata = [], string $id = 'payment-123'): PaymentContract
{
    return new class($metadata, $id) implements PaymentContract
    {
        public function __construct(
            private readonly array $metadata,
            private readonly string $id,
        ) {}

        public function id(): string
        {
            return $this->id;
        }

        public function gateway(): string
        {
            return 'test';
        }

        public function rawAmount(): int
        {
            return 100;
        }

        public function amount(): string
        {
            return '1.00 TEST';
        }

        public function currency(): string
        {
            return 'TEST';
        }

        public function status(): string
        {
            return 'succeeded';
        }

        public function metadata(): array
        {
            return $this->metadata;
        }

        public function errorCode(): ?string
        {
            return null;
        }

        public function isPending(): bool
        {
            return false;
        }

        public function isSucceeded(): bool
        {
            return true;
        }

        public function isFailed(): bool
        {
            return false;
        }

        public function isCanceled(): bool
        {
            return false;
        }

        public function requiresAction(): bool
        {
            return false;
        }

        public function requiresRedirect(): bool
        {
            return false;
        }

        public function redirectUrl(): ?string
        {
            return null;
        }

        public function receiptUrl(): ?string
        {
            return null;
        }

        public function validate(): static
        {
            return $this;
        }

        public function asGatewayPayment(): mixed
        {
            return null;
        }

        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'metadata' => $this->metadata,
            ];
        }

        public function toJson($options = 0): string
        {
            return json_encode($this->toArray(), $options) ?: '{}';
        }
    };
}

describe('CommitInventoryOnPayment', function (): void {
    it('commits allocations for events implementing ProvidesInventoryCommitContext', function (): void {
        $this->allocationService->allocate($this->item, 10, 'commit-cart-123', 30);

        $event = new class implements ProvidesInventoryCommitContext
        {
            public function inventoryCartId(): ?string
            {
                return 'commit-cart-123';
            }

            public function inventoryOrderReference(): ?string
            {
                return 'order-typed-123';
            }
        };

        $this->listener->handle($event);

        expect(InventoryAllocation::where('cart_id', 'commit-cart-123')->count())->toBe(0);
    });

    it('commits allocations for cashier payment succeeded events using typed metadata', function (): void {
        $this->allocationService->allocate($this->item, 5, 'cashier-cart-123', 30);

        $event = new CashierPaymentSucceeded(
            makeTypedPayment([
                'cart_id' => 'cashier-cart-123',
                'order_id' => 'cashier-order-123',
            ], 'payment-fallback-1'),
            'test',
            $this->item,
        );

        $this->listener->handle($event);

        expect(InventoryAllocation::where('cart_id', 'cashier-cart-123')->count())->toBe(0);
    });

    it('falls back to the payment id when cashier metadata has no order_id', function (): void {
        $this->allocationService->allocate($this->item, 5, 'cashier-cart-fallback', 30);

        $event = new CashierPaymentSucceeded(
            makeTypedPayment([
                'cart_id' => 'cashier-cart-fallback',
            ], 'payment-fallback-2'),
            'test',
            $this->item,
        );

        $this->listener->handle($event);

        expect(InventoryAllocation::where('cart_id', 'cashier-cart-fallback')->count())->toBe(0);
    });

    it('commits allocations for cashier chip payment succeeded events using typed purchase accessors', function (): void {
        $this->allocationService->allocate($this->item, 5, 'chip-cart-123', 30);

        $event = new CashierChipPaymentSucceeded($this->item, [
            'metadata' => [
                'cart_id' => 'chip-cart-123',
            ],
            'reference' => 'chip-order-123',
        ]);

        $this->listener->handle($event);

        expect(InventoryAllocation::where('cart_id', 'chip-cart-123')->count())->toBe(0);
    });

    it('ignores events that do not provide inventory commit context', function (): void {
        $event = new class {};

        $this->listener->handle($event);

        expect(true)->toBeTrue();
    });
});
