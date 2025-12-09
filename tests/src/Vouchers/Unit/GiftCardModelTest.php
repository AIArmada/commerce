<?php

declare(strict_types=1);

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GiftCard Model', function (): void {
    it('can create a gift card with default values', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        expect($giftCard)->toBeInstanceOf(GiftCard::class)
            ->and($giftCard->id)->not->toBeEmpty()
            ->and($giftCard->code)->toStartWith('GC-')
            ->and($giftCard->type)->toBe(GiftCardType::Standard)
            ->and($giftCard->status)->toBe(GiftCardStatus::Inactive)
            ->and($giftCard->currency)->toBe('MYR')
            ->and($giftCard->initial_balance)->toBe(10000)
            ->and($giftCard->current_balance)->toBe(10000);
    });

    it('generates unique codes automatically', function (): void {
        $card1 = GiftCard::create(['initial_balance' => 5000, 'current_balance' => 5000]);
        $card2 = GiftCard::create(['initial_balance' => 5000, 'current_balance' => 5000]);

        expect($card1->code)->not->toBe($card2->code);
    });

    it('uppercases codes on creation', function (): void {
        $giftCard = GiftCard::create([
            'code' => 'gc-test-1234',
            'initial_balance' => 5000,
            'current_balance' => 5000,
        ]);

        expect($giftCard->code)->toBe('GC-TEST-1234');
    });

    it('has transactions relationship', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Issue,
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
        ]);

        expect($giftCard->transactions)->toHaveCount(1)
            ->and($giftCard->transactions->first()->type)->toBe(GiftCardTransactionType::Issue);
    });

    it('calculates used balance correctly', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 6000,
        ]);

        expect($giftCard->used_balance)->toBe(4000);
    });

    it('calculates balance utilization correctly', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 2500,
        ]);

        expect($giftCard->balance_utilization)->toBe(75.0);
    });

    it('handles zero initial balance for utilization', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);

        expect($giftCard->balance_utilization)->toBe(0.0);
    });
});

describe('GiftCard Status Checks', function (): void {
    it('can check if gift card is active', function (): void {
        $active = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $inactive = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Inactive,
        ]);

        expect($active->isActive())->toBeTrue()
            ->and($inactive->isActive())->toBeFalse();
    });

    it('can check if gift card is expired', function (): void {
        $expired = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Expired,
        ]);

        $expiredByDate = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->subDay(),
        ]);

        $notExpired = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->addDay(),
        ]);

        expect($expired->isExpired())->toBeTrue()
            ->and($expiredByDate->isExpired())->toBeTrue()
            ->and($notExpired->isExpired())->toBeFalse();
    });

    it('can check if gift card has balance', function (): void {
        $withBalance = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
        ]);

        $noBalance = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 0,
        ]);

        expect($withBalance->hasBalance())->toBeTrue()
            ->and($noBalance->hasBalance())->toBeFalse();
    });

    it('can check if gift card can be redeemed', function (): void {
        $redeemable = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
        ]);

        $notActive = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Inactive,
        ]);

        $noBalance = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 0,
            'status' => GiftCardStatus::Active,
        ]);

        $expired = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->subDay(),
        ]);

        expect($redeemable->canRedeem())->toBeTrue()
            ->and($notActive->canRedeem())->toBeFalse()
            ->and($noBalance->canRedeem())->toBeFalse()
            ->and($expired->canRedeem())->toBeFalse();
    });

    it('can check if gift card can be topped up', function (): void {
        $active = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'type' => GiftCardType::Standard,
        ]);

        $promotional = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'type' => GiftCardType::Promotional,
        ]);

        expect($active->canTopUp())->toBeTrue()
            ->and($promotional->canTopUp())->toBeFalse();
    });

    it('can check if gift card can be transferred', function (): void {
        $standard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'type' => GiftCardType::Standard,
        ]);

        $promotional = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'type' => GiftCardType::Promotional,
        ]);

        expect($standard->canTransfer())->toBeTrue()
            ->and($promotional->canTransfer())->toBeFalse();
    });
});

describe('GiftCard PIN Verification', function (): void {
    it('verifies correct PIN', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'pin' => '1234',
        ]);

        expect($giftCard->verifyPin('1234'))->toBeTrue()
            ->and($giftCard->verifyPin('0000'))->toBeFalse();
    });

    it('allows any PIN when no PIN is set', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'pin' => null,
        ]);

        expect($giftCard->verifyPin(null))->toBeTrue()
            ->and($giftCard->verifyPin('1234'))->toBeTrue();
    });
});

describe('GiftCard Status Transitions', function (): void {
    it('can activate an inactive gift card', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Inactive,
        ]);

        $giftCard->activate();

        expect($giftCard->status)->toBe(GiftCardStatus::Active)
            ->and($giftCard->activated_at)->not->toBeNull();
    });

    it('records transaction on activation', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Inactive,
        ]);

        $giftCard->activate();

        expect($giftCard->transactions)->toHaveCount(1)
            ->and($giftCard->transactions->first()->type)->toBe(GiftCardTransactionType::Activate);
    });

    it('throws exception when activating from invalid status', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Cancelled,
        ]);

        expect(fn () => $giftCard->activate())->toThrow(RuntimeException::class);
    });

    it('can suspend an active gift card', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $giftCard->suspend();

        expect($giftCard->status)->toBe(GiftCardStatus::Suspended);
    });

    it('can cancel a gift card', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $giftCard->cancel();

        expect($giftCard->status)->toBe(GiftCardStatus::Cancelled);
    });
});

describe('GiftCard Balance Operations', function (): void {
    it('can credit balance', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $transaction = $giftCard->credit(
            amount: 5000,
            type: GiftCardTransactionType::TopUp,
            description: 'Test top up'
        );

        expect($giftCard->current_balance)->toBe(15000)
            ->and($transaction->amount)->toBe(5000)
            ->and($transaction->balance_before)->toBe(10000)
            ->and($transaction->balance_after)->toBe(15000);
    });

    it('throws exception on invalid credit amount', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        expect(fn () => $giftCard->credit(0, GiftCardTransactionType::TopUp))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception on wrong transaction type for credit', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        expect(fn () => $giftCard->credit(5000, GiftCardTransactionType::Redeem))
            ->toThrow(InvalidArgumentException::class);
    });

    it('can debit balance', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $transaction = $giftCard->debit(
            amount: 3000,
            type: GiftCardTransactionType::Redeem,
            description: 'Test redemption'
        );

        expect($giftCard->current_balance)->toBe(7000)
            ->and($transaction->amount)->toBe(-3000)
            ->and($transaction->balance_before)->toBe(10000)
            ->and($transaction->balance_after)->toBe(7000);
    });

    it('throws exception on insufficient balance', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
        ]);

        expect(fn () => $giftCard->debit(6000, GiftCardTransactionType::Redeem))
            ->toThrow(RuntimeException::class, 'Insufficient balance');
    });

    it('marks as exhausted when balance reaches zero', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
        ]);

        $giftCard->debit(5000, GiftCardTransactionType::Redeem);

        expect($giftCard->current_balance)->toBe(0)
            ->and($giftCard->status)->toBe(GiftCardStatus::Exhausted);
    });

    it('reactivates exhausted card on top up', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 0,
            'status' => GiftCardStatus::Exhausted,
            'type' => GiftCardType::Standard,
        ]);

        $giftCard->credit(5000, GiftCardTransactionType::TopUp);

        expect($giftCard->current_balance)->toBe(5000)
            ->and($giftCard->status)->toBe(GiftCardStatus::Active);
    });
});

describe('GiftCard Redemption', function (): void {
    it('can redeem for an order', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        // Create a mock order
        $order = new class extends Model
        {
            public $id = 'order-123';

            protected $table = 'vouchers';
        };

        $transaction = $giftCard->redeem(5000, $order);

        expect($giftCard->current_balance)->toBe(5000)
            ->and($transaction->type)->toBe(GiftCardTransactionType::Redeem)
            ->and($giftCard->last_used_at)->not->toBeNull();
    });

    it('throws exception when cannot redeem', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Inactive,
        ]);

        $order = new class extends Model
        {
            public $id = 'order-123';

            protected $table = 'vouchers';
        };

        expect(fn () => $giftCard->redeem(5000, $order))
            ->toThrow(RuntimeException::class, 'cannot be redeemed');
    });
});

describe('GiftCard Top Up', function (): void {
    it('can top up balance', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'type' => GiftCardType::Standard,
        ]);

        $transaction = $giftCard->topUp(3000);

        expect($giftCard->current_balance)->toBe(8000)
            ->and($transaction->type)->toBe(GiftCardTransactionType::TopUp);
    });

    it('throws exception when cannot top up', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'type' => GiftCardType::Promotional, // Cannot top up promotional
        ]);

        expect(fn () => $giftCard->topUp(3000))
            ->toThrow(RuntimeException::class, 'cannot be topped up');
    });
});

describe('GiftCard Static Methods', function (): void {
    it('can generate unique codes', function (): void {
        $code1 = GiftCard::generateCode();
        $code2 = GiftCard::generateCode();

        expect($code1)->toStartWith('GC-')
            ->and(mb_strlen($code1))->toBe(22) // GC- + 4 segments of 4 + 3 dashes
            ->and($code1)->not->toBe($code2);
    });

    it('can generate codes with custom prefix', function (): void {
        $code = GiftCard::generateCode('GIFT');

        expect($code)->toStartWith('GIFT-');
    });

    it('can find by code', function (): void {
        $giftCard = GiftCard::create([
            'code' => 'GC-TEST-FIND',
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $found = GiftCard::findByCode('GC-TEST-FIND');
        $notFound = GiftCard::findByCode('GC-DOES-NOT-EXIST');

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($giftCard->id)
            ->and($notFound)->toBeNull();
    });

    it('can find by code or fail', function (): void {
        $giftCard = GiftCard::create([
            'code' => 'GC-TEST-FAIL',
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $found = GiftCard::findByCodeOrFail('GC-TEST-FAIL');
        expect($found->id)->toBe($giftCard->id);

        expect(fn () => GiftCard::findByCodeOrFail('GC-NOT-FOUND'))
            ->toThrow(RuntimeException::class, 'Gift card not found');
    });
});

describe('GiftCard Scopes', function (): void {
    beforeEach(function (): void {
        GiftCard::create(['initial_balance' => 10000, 'current_balance' => 10000, 'status' => GiftCardStatus::Active]);
        GiftCard::create(['initial_balance' => 10000, 'current_balance' => 5000, 'status' => GiftCardStatus::Active]);
        GiftCard::create(['initial_balance' => 10000, 'current_balance' => 0, 'status' => GiftCardStatus::Exhausted]);
        GiftCard::create(['initial_balance' => 10000, 'current_balance' => 10000, 'status' => GiftCardStatus::Inactive]);
    });

    it('can filter active gift cards', function (): void {
        $active = GiftCard::active()->get();

        expect($active)->toHaveCount(2);
    });

    it('can filter by status', function (): void {
        $exhausted = GiftCard::withStatus(GiftCardStatus::Exhausted)->get();

        expect($exhausted)->toHaveCount(1);
    });

    it('can filter with balance', function (): void {
        $withBalance = GiftCard::withBalance()->get();

        expect($withBalance)->toHaveCount(3);
    });
});

describe('GiftCard Cascade Delete', function (): void {
    it('deletes transactions when gift card is deleted', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Issue,
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
        ]);

        $giftCardId = $giftCard->id;
        $giftCard->delete();

        expect(GiftCardTransaction::where('gift_card_id', $giftCardId)->count())->toBe(0);
    });
});
