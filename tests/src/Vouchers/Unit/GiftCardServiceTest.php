<?php

declare(strict_types=1);

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new GiftCardService();
});

describe('GiftCardService Issue', function (): void {
    it('can issue a new gift card', function (): void {
        $giftCard = $this->service->issue([
            'initial_balance' => 10000,
        ]);

        expect($giftCard)->toBeInstanceOf(GiftCard::class)
            ->and($giftCard->initial_balance)->toBe(10000)
            ->and($giftCard->current_balance)->toBe(10000)
            ->and($giftCard->type)->toBe(GiftCardType::Standard)
            ->and($giftCard->status)->toBe(GiftCardStatus::Inactive);
    });

    it('records issue transaction', function (): void {
        $giftCard = $this->service->issue([
            'initial_balance' => 10000,
        ]);

        expect($giftCard->transactions)->toHaveCount(1)
            ->and($giftCard->transactions->first()->type)->toBe(GiftCardTransactionType::Issue)
            ->and($giftCard->transactions->first()->amount)->toBe(10000);
    });

    it('can issue with custom type', function (): void {
        $giftCard = $this->service->issue([
            'initial_balance' => 10000,
            'type' => GiftCardType::Promotional,
        ]);

        expect($giftCard->type)->toBe(GiftCardType::Promotional);
    });

    it('can issue with custom code', function (): void {
        $giftCard = $this->service->issue([
            'initial_balance' => 10000,
            'code' => 'CUSTOM-CODE-123',
        ]);

        expect($giftCard->code)->toBe('CUSTOM-CODE-123');
    });
});

describe('GiftCardService Purchase', function (): void {
    it('can purchase a gift card for self', function (): void {
        $purchaser = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string
            {
                return 'user-123';
            }
        };

        $giftCard = $this->service->purchase(10000, $purchaser);

        expect($giftCard->initial_balance)->toBe(10000)
            ->and($giftCard->purchaser_id)->toBe('user-123')
            ->and($giftCard->recipient_id)->toBe('user-123');
    });

    it('can purchase a gift card for another recipient', function (): void {
        $purchaser = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string
            {
                return 'user-123';
            }
        };

        $recipient = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string
            {
                return 'user-456';
            }
        };

        $giftCard = $this->service->purchase(10000, $purchaser, $recipient);

        expect($giftCard->purchaser_id)->toBe('user-123')
            ->and($giftCard->recipient_id)->toBe('user-456');
    });
});

describe('GiftCardService CreateBulk', function (): void {
    it('can create bulk gift cards', function (): void {
        $giftCards = $this->service->createBulk(5, 10000);

        expect($giftCards)->toHaveCount(5);

        foreach ($giftCards as $giftCard) {
            expect($giftCard->initial_balance)->toBe(10000);
        }
    });

    it('creates unique codes for bulk gift cards', function (): void {
        $giftCards = $this->service->createBulk(3, 10000);

        $codes = $giftCards->pluck('code')->toArray();

        expect(array_unique($codes))->toHaveCount(3);
    });
});

describe('GiftCardService Activate', function (): void {
    it('can activate a gift card by code', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);

        $activated = $this->service->activate($giftCard->code);

        expect($activated->status)->toBe(GiftCardStatus::Active)
            ->and($activated->activated_at)->not->toBeNull();
    });

    it('can activate with delay', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $futureDate = now()->addDays(7);

        $result = $this->service->activateWithDelay($giftCard->code, $futureDate);

        expect($result->status)->toBe(GiftCardStatus::Inactive)
            ->and($result->metadata['scheduled_activation_at'])->not->toBeNull();
    });

    it('activates immediately if delay is in past', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $pastDate = now()->subDay();

        $result = $this->service->activateWithDelay($giftCard->code, $pastDate);

        expect($result->status)->toBe(GiftCardStatus::Active);
    });
});

describe('GiftCardService Balance Operations', function (): void {
    it('can check balance', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);

        $balance = $this->service->checkBalance($giftCard->code);

        expect($balance)->toBe(10000);
    });

    it('can top up balance', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $actor = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'admin-123'; }
        };

        $updated = $this->service->topUp($giftCard->code, 5000, $actor);

        expect($updated->current_balance)->toBe(15000);
    });
});

describe('GiftCardService Transfer', function (): void {
    it('can transfer to new recipient', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $newRecipient = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'user-new'; }
        };

        $actor = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'admin-123'; }
        };

        $transferred = $this->service->transfer($giftCard->code, $newRecipient, $actor);

        expect($transferred->recipient_id)->toBe('user-new');
    });
});

describe('GiftCardService Merge', function (): void {
    it('can merge multiple gift cards', function (): void {
        $card1 = $this->service->issue(['initial_balance' => 5000]);
        $card1->activate();

        $card2 = $this->service->issue(['initial_balance' => 3000]);
        $card2->activate();

        $card3 = $this->service->issue(['initial_balance' => 2000]);
        $card3->activate();

        $actor = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'admin-123'; }
        };

        $merged = $this->service->merge(
            [$card1->code, $card2->code, $card3->code],
            $actor
        );

        expect($merged->id)->toBe($card1->id)
            ->and($merged->current_balance)->toBe(10000);

        // Source cards should be cancelled
        $card2->refresh();
        $card3->refresh();

        expect($card2->status)->toBe(GiftCardStatus::Cancelled)
            ->and($card3->status)->toBe(GiftCardStatus::Cancelled);
    });

    it('throws exception for less than 2 cards', function (): void {
        $card1 = $this->service->issue(['initial_balance' => 5000]);
        $card1->activate();

        $actor = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'admin-123'; }
        };

        expect(fn() => $this->service->merge([$card1->code], $actor))
            ->toThrow(InvalidArgumentException::class, 'At least 2 gift cards required');
    });
});

describe('GiftCardService Redeem and Refund', function (): void {
    it('can redeem', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $order = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'order-123'; }
        };

        $transaction = $this->service->redeem($giftCard->code, 3000, $order);

        expect($transaction->type)->toBe(GiftCardTransactionType::Redeem)
            ->and($transaction->amount)->toBe(-3000);

        $giftCard->refresh();
        expect($giftCard->current_balance)->toBe(7000);
    });

    it('can refund', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();
        $giftCard->current_balance = 7000;
        $giftCard->save();

        $order = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'order-123'; }
        };

        $transaction = $this->service->refund($giftCard->code, 3000, $order);

        expect($transaction->type)->toBe(GiftCardTransactionType::Refund)
            ->and($transaction->amount)->toBe(3000);

        $giftCard->refresh();
        expect($giftCard->current_balance)->toBe(10000);
    });
});

describe('GiftCardService Queries', function (): void {
    it('can find by code', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);

        $found = $this->service->findByCode($giftCard->code);
        $notFound = $this->service->findByCode('DOES-NOT-EXIST');

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($giftCard->id)
            ->and($notFound)->toBeNull();
    });

    it('can get by recipient', function (): void {
        $recipient = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';

            public function getMorphClass(): string
            {
                return 'vouchers';
            }

            public function getKey(): string
            {
                return 'user-123';
            }
        };

        $card1 = $this->service->issue([
            'initial_balance' => 10000,
            'recipient_type' => 'vouchers',
            'recipient_id' => 'user-123',
        ]);

        $card2 = $this->service->issue([
            'initial_balance' => 5000,
            'recipient_type' => 'vouchers',
            'recipient_id' => 'user-123',
        ]);

        $this->service->issue([
            'initial_balance' => 5000,
            'recipient_type' => 'vouchers',
            'recipient_id' => 'user-456',
        ]);

        $giftCards = $this->service->getByRecipient($recipient);

        expect($giftCards)->toHaveCount(2);
    });

    it('can get expiring gift cards', function (): void {
        $expiringSoon = $this->service->issue(['initial_balance' => 10000]);
        $expiringSoon->update([
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->addDays(15),
        ]);

        $expiringLater = $this->service->issue(['initial_balance' => 10000]);
        $expiringLater->update([
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->addDays(60),
        ]);

        $noExpiry = $this->service->issue(['initial_balance' => 10000]);
        $noExpiry->update(['status' => GiftCardStatus::Active]);

        $expiring = $this->service->getExpiring(30);

        expect($expiring)->toHaveCount(1)
            ->and($expiring->first()->id)->toBe($expiringSoon->id);
    });

    it('can get active with balance', function (): void {
        $active = $this->service->issue(['initial_balance' => 10000]);
        $active->activate();

        $exhausted = $this->service->issue(['initial_balance' => 10000]);
        $exhausted->activate();
        $exhausted->current_balance = 0;
        $exhausted->status = GiftCardStatus::Exhausted;
        $exhausted->save();

        $inactive = $this->service->issue(['initial_balance' => 10000]);

        $giftCards = $this->service->getActiveWithBalance();

        expect($giftCards)->toHaveCount(1)
            ->and($giftCards->first()->id)->toBe($active->id);
    });
});

describe('GiftCardService Status Changes', function (): void {
    it('can suspend', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $suspended = $this->service->suspend($giftCard->code);

        expect($suspended->status)->toBe(GiftCardStatus::Suspended);
    });

    it('can cancel', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $cancelled = $this->service->cancel($giftCard->code);

        expect($cancelled->status)->toBe(GiftCardStatus::Cancelled);
    });

    it('can expire', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $actor = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'vouchers';
            public function getKey(): string { return 'admin-123'; }
        };

        $expired = $this->service->expire($giftCard->code, $actor);

        expect($expired->status)->toBe(GiftCardStatus::Expired)
            ->and($expired->current_balance)->toBe(0);
    });
});

describe('GiftCardService Statistics', function (): void {
    it('can get statistics', function (): void {
        $card1 = $this->service->issue(['initial_balance' => 10000]);
        $card1->activate();

        $card2 = $this->service->issue(['initial_balance' => 5000]);
        $card2->activate();
        $card2->current_balance = 2000;
        $card2->save();

        $card3 = $this->service->issue(['initial_balance' => 3000]);

        $stats = $this->service->getStatistics();

        expect($stats['total_cards'])->toBe(3)
            ->and($stats['active_cards'])->toBe(2)
            ->and($stats['total_issued_cents'])->toBe(18000)
            ->and($stats['total_redeemed_cents'])->toBe(3000)
            ->and($stats['total_outstanding_cents'])->toBe(15000);
    });
});

describe('GiftCardService Validation', function (): void {
    it('validates a valid gift card', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();

        $result = $this->service->validate($giftCard->code);

        expect($result['valid'])->toBeTrue()
            ->and($result['message'])->toBeNull()
            ->and($result['available_balance'])->toBe(10000);
    });

    it('validates non-existent gift card', function (): void {
        $result = $this->service->validate('DOES-NOT-EXIST');

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Gift card not found');
    });

    it('validates incorrect PIN', function (): void {
        $giftCard = $this->service->issue([
            'initial_balance' => 10000,
            'pin' => '1234',
        ]);
        $giftCard->activate();

        $result = $this->service->validate($giftCard->code, '0000');

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Invalid PIN');
    });

    it('validates inactive gift card', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);

        $result = $this->service->validate($giftCard->code);

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Gift card is not active');
    });

    it('validates expired gift card', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();
        $giftCard->expires_at = now()->subDay();
        $giftCard->save();

        $result = $this->service->validate($giftCard->code);

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Gift card has expired');
    });

    it('validates zero balance gift card', function (): void {
        $giftCard = $this->service->issue(['initial_balance' => 10000]);
        $giftCard->activate();
        $giftCard->current_balance = 0;
        $giftCard->save();

        $result = $this->service->validate($giftCard->code);

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Gift card has no balance');
    });
});

describe('GiftCardService Process Scheduled', function (): void {
    it('can process scheduled activations', function (): void {
        $card1 = $this->service->issue(['initial_balance' => 10000]);
        $card1->metadata = ['scheduled_activation_at' => now()->subHour()->toIso8601String()];
        $card1->save();

        $card2 = $this->service->issue(['initial_balance' => 10000]);
        $card2->metadata = ['scheduled_activation_at' => now()->addHour()->toIso8601String()];
        $card2->save();

        $count = $this->service->processScheduledActivations();

        expect($count)->toBe(1);

        $card1->refresh();
        $card2->refresh();

        expect($card1->status)->toBe(GiftCardStatus::Active)
            ->and($card2->status)->toBe(GiftCardStatus::Inactive);
    });

    it('can process expired gift cards', function (): void {
        $expired = $this->service->issue(['initial_balance' => 10000]);
        $expired->activate();
        $expired->expires_at = now()->subHour();
        $expired->save();

        $notExpired = $this->service->issue(['initial_balance' => 10000]);
        $notExpired->activate();
        $notExpired->expires_at = now()->addHour();
        $notExpired->save();

        $count = $this->service->processExpired();

        expect($count)->toBe(1);

        $expired->refresh();
        $notExpired->refresh();

        expect($expired->status)->toBe(GiftCardStatus::Expired)
            ->and($notExpired->status)->toBe(GiftCardStatus::Active);
    });
});
