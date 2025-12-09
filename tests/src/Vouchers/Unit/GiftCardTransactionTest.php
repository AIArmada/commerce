<?php

declare(strict_types=1);

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GiftCardTransaction Model', function (): void {
    it('can create a transaction', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $transaction = GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Issue,
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
            'description' => 'Initial issue',
        ]);

        expect($transaction)->toBeInstanceOf(GiftCardTransaction::class)
            ->and($transaction->id)->not->toBeEmpty()
            ->and($transaction->type)->toBe(GiftCardTransactionType::Issue)
            ->and($transaction->amount)->toBe(10000)
            ->and($transaction->balance_before)->toBe(0)
            ->and($transaction->balance_after)->toBe(10000);
    });

    it('has gift card relationship', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $transaction = GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Issue,
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
        ]);

        expect($transaction->giftCard)->toBeInstanceOf(GiftCard::class)
            ->and($transaction->giftCard->id)->toBe($giftCard->id);
    });

    it('identifies credit transactions', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $credit = GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::TopUp,
            'amount' => 5000,
            'balance_before' => 10000,
            'balance_after' => 15000,
        ]);

        $debit = GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Redeem,
            'amount' => -3000,
            'balance_before' => 15000,
            'balance_after' => 12000,
        ]);

        expect($credit->is_credit)->toBeTrue()
            ->and($credit->is_debit)->toBeFalse()
            ->and($debit->is_credit)->toBeFalse()
            ->and($debit->is_debit)->toBeTrue();
    });

    it('can get absolute amount', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $transaction = GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Redeem,
            'amount' => -5000,
            'balance_before' => 10000,
            'balance_after' => 5000,
        ]);

        expect($transaction->getAbsoluteAmount())->toBe(5000);
    });
});

describe('GiftCardTransaction Scopes', function (): void {
    beforeEach(function (): void {
        $this->giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        GiftCardTransaction::create([
            'gift_card_id' => $this->giftCard->id,
            'type' => GiftCardTransactionType::Issue,
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
        ]);

        GiftCardTransaction::create([
            'gift_card_id' => $this->giftCard->id,
            'type' => GiftCardTransactionType::TopUp,
            'amount' => 5000,
            'balance_before' => 10000,
            'balance_after' => 15000,
        ]);

        GiftCardTransaction::create([
            'gift_card_id' => $this->giftCard->id,
            'type' => GiftCardTransactionType::Redeem,
            'amount' => -3000,
            'balance_before' => 15000,
            'balance_after' => 12000,
        ]);
    });

    it('can filter by type', function (): void {
        $redemptions = GiftCardTransaction::ofType(GiftCardTransactionType::Redeem)->get();

        expect($redemptions)->toHaveCount(1);
    });

    it('can filter credits', function (): void {
        $credits = GiftCardTransaction::credits()->get();

        expect($credits)->toHaveCount(2); // Issue + TopUp
    });

    it('can filter debits', function (): void {
        $debits = GiftCardTransaction::debits()->get();

        expect($debits)->toHaveCount(1); // Redeem
    });

    it('can filter by date range', function (): void {
        $yesterday = now()->subDay();
        $tomorrow = now()->addDay();

        $transactions = GiftCardTransaction::occurredBetween($yesterday, $tomorrow)->get();

        expect($transactions)->toHaveCount(3);
    });
});

describe('GiftCardTransaction Factory Methods', function (): void {
    it('can record redemption', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $order = new class extends Model
        {
            public $id = 'order-123';

            protected $table = 'vouchers';
        };

        $transaction = GiftCardTransaction::recordRedemption(
            giftCard: $giftCard,
            amount: 5000,
            reference: $order,
            description: 'Order payment'
        );

        expect($transaction->type)->toBe(GiftCardTransactionType::Redeem)
            ->and($transaction->amount)->toBe(-5000)
            ->and($transaction->balance_before)->toBe(10000)
            ->and($transaction->balance_after)->toBe(5000)
            ->and($transaction->description)->toBe('Order payment');
    });

    it('can record top up', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
        ]);

        $transaction = GiftCardTransaction::recordTopUp(
            giftCard: $giftCard,
            amount: 5000,
            description: 'Birthday bonus'
        );

        expect($transaction->type)->toBe(GiftCardTransactionType::TopUp)
            ->and($transaction->amount)->toBe(5000)
            ->and($transaction->balance_before)->toBe(10000)
            ->and($transaction->balance_after)->toBe(15000);
    });

    it('can record refund', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
        ]);

        $order = new class extends Model
        {
            public $id = 'order-123';

            protected $table = 'vouchers';
        };

        $transaction = GiftCardTransaction::recordRefund(
            giftCard: $giftCard,
            amount: 3000,
            reference: $order,
            description: 'Order refund'
        );

        expect($transaction->type)->toBe(GiftCardTransactionType::Refund)
            ->and($transaction->amount)->toBe(3000)
            ->and($transaction->balance_before)->toBe(5000)
            ->and($transaction->balance_after)->toBe(8000);
    });
});

describe('GiftCardTransaction Metadata', function (): void {
    it('can store and retrieve metadata', function (): void {
        $giftCard = GiftCard::create([
            'initial_balance' => 10000,
            'current_balance' => 10000,
        ]);

        $transaction = GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Transfer,
            'amount' => 0,
            'balance_before' => 10000,
            'balance_after' => 10000,
            'metadata' => [
                'previous_recipient_id' => 'user-old',
                'new_recipient_id' => 'user-new',
            ],
        ]);

        expect($transaction->metadata)->toBeArray()
            ->and($transaction->metadata['previous_recipient_id'])->toBe('user-old')
            ->and($transaction->metadata['new_recipient_id'])->toBe('user-new');
    });
});
