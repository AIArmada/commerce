<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Feature;

use AIArmada\CashierChip\Actions\RefundChipPayment;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Concerns\PerformsCharges;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Commerce\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

\uses(TestCase::class);
describe('RefundChipPayment', function (): void {
    it('refunds a purchase', function (): void {
        $fakeClient = new FakeChipClient;
        $purchase = $fakeClient->createPurchase(['status' => 'paid', 'purchase' => ['total' => 5000, 'currency' => 'MYR']]);
        $purchaseId = $purchase['id'];

        Cashier::fake($fakeClient);

        $result = RefundChipPayment::run($purchaseId);

        expect($result)->toBeInstanceOf(PaymentData::class)
            ->and($result->payment_type)->toBe('refund');
    });

    it('refunds a purchase with partial amount', function (): void {
        $fakeClient = new FakeChipClient;
        $purchase = $fakeClient->createPurchase(['status' => 'paid', 'purchase' => ['total' => 5000, 'currency' => 'MYR']]);
        $purchaseId = $purchase['id'];

        Cashier::fake($fakeClient);

        $result = RefundChipPayment::run($purchaseId, 2500);

        expect($result)->toBeInstanceOf(PaymentData::class)
            ->and($result->payment_type)->toBe('refund');
    });
});

it('rejects refunds for a purchase owned by another billable customer', function (): void {
    $fakeClient = new FakeChipClient;
    $purchase = $fakeClient->createPurchase([
        'client_id' => 'cli_someone_else',
        'status' => 'paid',
        'purchase' => ['total' => 5000, 'currency' => 'MYR'],
    ]);

    Cashier::fake($fakeClient);

    $billable = new class extends Model
    {
        use PerformsCharges;

        public function chipId(): ?string
        {
            return 'cli_current_billable';
        }

        public function hasChipId(): bool
        {
            return true;
        }

        public function chipEmail(): ?string
        {
            return 'owner@example.com';
        }

        public function chipName(): ?string
        {
            return 'Owner';
        }

        public function preferredCurrency(): string
        {
            return 'MYR';
        }

        public function getMorphClass(): string
        {
            return 'refund-billable';
        }

        public function getKey(): mixed
        {
            return 'refund-billable-1';
        }
    };

    expect(fn () => $billable->refund($purchase['id']))
        ->toThrow(AuthorizationException::class);
});
