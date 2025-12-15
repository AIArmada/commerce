<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\Concerns\HasVoucherEventData;
use DateTimeImmutable;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

/**
 * Test class using the HasVoucherEventData trait.
 */
class TestVoucherEvent
{
    use HasVoucherEventData;

    public VoucherData $voucher;

    public Cart $cart;

    public function __construct(VoucherData $voucher, Cart $cart)
    {
        $this->voucher = $voucher;
        $this->cart = $cart;
        $this->initializeEventData();
    }

    public function getEventType(): string
    {
        return 'test_voucher_event';
    }
}

/**
 * Create a test voucher for event testing.
 */
function createEventTestVoucher(string $code = 'TEST10', float $value = 1000): VoucherData
{
    return VoucherData::fromArray([
        'id' => 'voucher-' . $code,
        'code' => $code,
        'name' => 'Test Voucher ' . $code,
        'type' => 'percentage',
        'value' => $value,
        'status' => 'active',
    ]);
}

/**
 * Create a test cart for event testing.
 */
function createEventTestCart(string $identifier = null): Cart
{
    return new Cart(
        new InMemoryStorage(),
        $identifier ?? 'test-cart-' . uniqid()
    );
}

describe('HasVoucherEventData', function (): void {
    describe('initializeEventData', function (): void {
        it('generates unique event id', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();

            $event1 = new TestVoucherEvent($voucher, $cart);
            $event2 = new TestVoucherEvent($voucher, $cart);

            expect($event1->getEventId())->not->toBe($event2->getEventId());
        });

        it('sets occurred at to current time', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();

            $before = new DateTimeImmutable();
            $event = new TestVoucherEvent($voucher, $cart);
            $after = new DateTimeImmutable();

            expect($event->getOccurredAt()->getTimestamp())
                ->toBeGreaterThanOrEqual($before->getTimestamp())
                ->toBeLessThanOrEqual($after->getTimestamp());
        });
    });

    describe('getEventId', function (): void {
        it('returns uuid string', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            $eventId = $event->getEventId();

            // UUID pattern
            expect($eventId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });
    });

    describe('getOccurredAt', function (): void {
        it('returns DateTimeImmutable', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->getOccurredAt())->toBeInstanceOf(DateTimeImmutable::class);
        });
    });

    describe('getVoucherCode', function (): void {
        it('returns voucher code', function (): void {
            $voucher = createEventTestVoucher('SUMMER20');
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->getVoucherCode())->toBe('SUMMER20');
        });
    });

    describe('getVoucherId', function (): void {
        it('returns voucher id', function (): void {
            $voucher = createEventTestVoucher('TEST10');
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->getVoucherId())->toBe('voucher-TEST10');
        });
    });

    describe('getCartIdentifier', function (): void {
        it('returns cart identifier', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart('my-cart-123');
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->getCartIdentifier())->toBe('my-cart-123');
        });
    });

    describe('getCartInstance', function (): void {
        it('returns cart instance name', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->getCartInstance())->toBe('default');
        });
    });

    describe('getDiscountAmountCents', function (): void {
        it('returns value multiplied by 100', function (): void {
            $voucher = createEventTestVoucher('TEST', 500); // 5.00 or 500 basis points
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->getDiscountAmountCents())->toBe(50000);
        });
    });

    describe('shouldPersist', function (): void {
        it('returns true by default', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            expect($event->shouldPersist())->toBeTrue();
        });
    });

    describe('withPersistence', function (): void {
        it('creates clone with persistence flag', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            $withoutPersist = $event->withPersistence(false);

            expect($event->shouldPersist())->toBeTrue();
            expect($withoutPersist->shouldPersist())->toBeFalse();
            expect($event)->not->toBe($withoutPersist);
        });
    });

    describe('withoutPersistence', function (): void {
        it('creates clone without persistence', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            $noPersist = $event->withoutPersistence();

            expect($noPersist->shouldPersist())->toBeFalse();
        });
    });

    describe('toEventPayload', function (): void {
        it('returns complete payload array', function (): void {
            $voucher = createEventTestVoucher('SAVE20', 2000);
            $cart = createEventTestCart('cart-123');
            $event = new TestVoucherEvent($voucher, $cart);

            $payload = $event->toEventPayload();

            expect($payload)->toHaveKey('event_type', 'test_voucher_event');
            expect($payload)->toHaveKey('event_id');
            expect($payload)->toHaveKey('occurred_at');
            expect($payload)->toHaveKey('voucher_code', 'SAVE20');
            expect($payload)->toHaveKey('voucher_id', 'voucher-SAVE20');
            expect($payload)->toHaveKey('cart_identifier', 'cart-123');
            expect($payload)->toHaveKey('cart_instance', 'default');
            expect($payload)->toHaveKey('discount_cents', 200000);
        });

        it('formats occurred_at as ISO 8601', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            $payload = $event->toEventPayload();

            expect($payload['occurred_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
        });
    });

    describe('getEventMetadata', function (): void {
        it('returns metadata with source and version', function (): void {
            $voucher = createEventTestVoucher();
            $cart = createEventTestCart();
            $event = new TestVoucherEvent($voucher, $cart);

            $metadata = $event->getEventMetadata();

            expect($metadata)->toHaveKey('source', 'vouchers');
            expect($metadata)->toHaveKey('version', '1.0');
            expect($metadata)->toHaveKey('timestamp');
        });
    });
});
