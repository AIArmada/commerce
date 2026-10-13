<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Actions\SyncChipPurchaseStatus;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SettledPeriodPurchaseConflict;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(CashierChipTestCase::class);

function f04Payload(string $purchaseId, int $total, string $currency, string $type = 'default'): array
{
    return [
        'id' => $purchaseId,
        'client_id' => 'cli_f04',
        'status' => 'paid',
        'purchase' => [
            'total' => $total,
            'currency' => $currency,
            'metadata' => ['subscription_type' => $type],
        ],
    ];
}

function f04Subscription(User $user, int $unitAmount, string $type = 'default'): Subscription
{
    /** @var Subscription $subscription */
    $subscription = $user->subscriptions()->make();
    $subscription->forceFill([
        'type' => $type,
        'chip_id' => 'sub_f04_' . uniqid(),
        'chip_status' => SubscriptionStatus::Active,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'next_billing_at' => CarbonImmutable::now()->subDay(),
    ]);
    $subscription->save();

    $item = $subscription->items()->make();
    $item->forceFill([
        'chip_id' => 'item_f04_' . uniqid(),
        'chip_product' => 'prod',
        'chip_price' => 'price_monthly',
        'quantity' => 1,
        'unit_amount' => $unitAmount,
    ]);
    $item->save();

    return $subscription->refresh();
}

describe('RenewalAmountMismatch', function (): void {
    it('settles on matching amount and records the actual charge as ledger truth', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at;

        $payload = f04Payload('pur_f04_match', 1000, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $fresh = $subscription->fresh();
        $attempt = RenewalAttempt::query()->where('purchase_id', 'pur_f04_match')->first();

        expect($fresh?->chip_status)->toBe(SubscriptionStatus::Active)
            ->and($fresh?->next_billing_at?->toIso8601String())->not->toBe($before?->toIso8601String())
            ->and($attempt?->status)->toBe('completed')
            ->and($attempt?->amount_minor)->toBe(1000);
    });

    it('completes only the claim for the settled billing period', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_f04_period_claim']);
        $subscription = f04Subscription($user, 1000);
        $currentClaim = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($currentClaim)->not->toBeNull();

        $olderClaim = RenewalAttempt::query()->create([
            'subscription_id' => $subscription->id,
            'status' => 'unknown',
            'amount_minor' => 1000,
            'period_key' => '1999-01',
            'lease_expires_at' => CarbonImmutable::now()->addHour(),
        ]);

        $payload = f04Payload('pur_f04_period_claim', 1000, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        expect($currentClaim?->fresh()?->status)->toBe('completed')
            ->and($currentClaim?->fresh()?->purchase_id)->toBe('pur_f04_period_claim')
            ->and($olderClaim->fresh()?->status)->toBe('unknown')
            ->and($olderClaim->fresh()?->purchase_id)->toBeNull();
    });

    it('holds settlement when the gateway charge differs from the frozen claim amount', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();

        $claimed = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($claimed)->not->toBeNull()
            ->and($claimed?->amount_minor)->toBe(1000);

        // The active claim freezes the amount that the renewal worker charges.
        $subscription->items()->update(['unit_amount' => 1500]);

        $payload = f04Payload('pur_f04_stale', 1500, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $mismatch = RenewalAttempt::query()->where('purchase_id', 'pur_f04_stale')->first();

        expect($claimed->fresh()?->status)->toBe('claimed')
            ->and($claimed->fresh()?->amount_minor)->toBe(1000)
            ->and($mismatch?->status)->toBe('mismatch')
            ->and($mismatch?->amount_minor)->toBe(1500)
            ->and($mismatch?->period_key)->toBeNull()
            ->and($mismatch?->last_error_code)->toBe('AMOUNT_MISMATCH')
            ->and($subscription->fresh()?->next_billing_at?->toIso8601String())->toBe($before);

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->expectedAmountMinor === 1000
            && $event->actualAmountMinor === 1500);

        $correctPayload = f04Payload('pur_f04_stale_claim', 1000, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($correctPayload), $correctPayload);

        expect($claimed->fresh()?->status)->toBe('completed')
            ->and($claimed->fresh()?->purchase_id)->toBe('pur_f04_stale_claim')
            ->and($claimed->fresh()?->amount_minor)->toBe(1000)
            ->and($subscription->fresh()?->next_billing_at?->isFuture())->toBeTrue();
    });

    it('holds settlement on underpayment and records a terminal mismatch', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);
        Log::spy();

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();
        $periodKey = ClaimRenewalAttempt::periodKeyFor($subscription);

        $payload = f04Payload('pur_f04_under', 600, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $fresh = $subscription->fresh();
        $attempt = RenewalAttempt::query()->where('purchase_id', 'pur_f04_under')->first();

        expect($fresh?->next_billing_at?->toIso8601String())->toBe($before)
            ->and($attempt?->status)->toBe('mismatch')
            ->and($attempt?->amount_minor)->toBe(600)
            ->and($attempt?->last_error_code)->toBe('AMOUNT_MISMATCH')
            ->and($attempt?->lease_expires_at)->toBeNull()
            ->and($attempt?->completed_at)->not->toBeNull();

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->purchaseId === 'pur_f04_under'
            && $event->periodKey === $periodKey
            && $event->expectedAmountMinor === 1000
            && $event->actualAmountMinor === 600
            && $event->expectedCurrency === 'MYR'
            && $event->actualCurrency === 'MYR');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'mismatch')
            && ($context['expected_amount_minor'] ?? null) === 1000
            && ($context['actual_amount_minor'] ?? null) === 600);
    });

    it('holds settlement on overpayment', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();

        $payload = f04Payload('pur_f04_over', 1400, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $attempt = RenewalAttempt::query()->where('purchase_id', 'pur_f04_over')->first();

        expect($subscription->fresh()?->next_billing_at?->toIso8601String())->toBe($before)
            ->and($attempt?->status)->toBe('mismatch')
            ->and($attempt?->amount_minor)->toBe(1400)
            ->and($attempt?->last_error_code)->toBe('AMOUNT_MISMATCH');

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->actualAmountMinor === 1400
            && $event->expectedAmountMinor === 1000);
    });

    it('holds settlement on currency mismatch', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();

        $payload = f04Payload('pur_f04_ccy', 1000, 'USD');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $attempt = RenewalAttempt::query()->where('purchase_id', 'pur_f04_ccy')->first();

        expect($subscription->fresh()?->next_billing_at?->toIso8601String())->toBe($before)
            ->and($attempt?->status)->toBe('mismatch')
            ->and($attempt?->last_error_code)->toBe('CURRENCY_MISMATCH');

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->actualCurrency === 'USD'
            && $event->expectedCurrency === 'MYR');
    });

    it('marks the exact claimed attempt as mismatch instead of creating a duplicate receipt', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);

        $claimed = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($claimed)->not->toBeNull();

        DB::table($claimed->getTable())->where('id', $claimed->id)->update(['purchase_id' => 'pur_f04_exact']);

        $payload = f04Payload('pur_f04_exact', 200, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $rows = RenewalAttempt::query()->where('subscription_id', $subscription->id)->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()?->status)->toBe('mismatch')
            ->and($rows->first()?->amount_minor)->toBe(200)
            ->and($subscription->fresh()?->next_billing_at?->isPast())->toBeTrue();
    });

    it('treats a mismatched purchase as already processed on redelivery', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);

        $payload = f04Payload('pur_f04_dup', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        expect(RenewalAttempt::query()->where('purchase_id', 'pur_f04_dup')->count())->toBe(1);

        Event::assertDispatchedTimes(SettledPeriodPurchaseConflict::class, 1);
    });

    it('suppresses the mismatch hook when the outer transaction rolls back', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $payload = f04Payload('pur_f04_rb', 100, 'MYR');

        try {
            DB::transaction(function () use ($user, $payload): void {
                SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

                throw new RuntimeException('outer rollback');
            });

            $this->fail('Outer transaction must roll back.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('outer rollback');
        }

        expect(RenewalAttempt::query()->where('purchase_id', 'pur_f04_rb')->count())->toBe(0)
            ->and($subscription->fresh()?->next_billing_at?->isPast())->toBeTrue();

        Event::assertNotDispatched(SettledPeriodPurchaseConflict::class);
    });

    it('records a null-period mismatch receipt when a null pending claim holds the period', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);
        Log::spy();

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();
        $periodKey = ClaimRenewalAttempt::periodKeyFor($subscription);

        $pending = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($pending)->not->toBeNull()
            ->and($pending?->purchase_id)->toBeNull();

        $payload = f04Payload('pur_f04_unrelated', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        // Pending claim untouched and still owns the period; billing held.
        expect($pending->fresh()?->status)->toBe('claimed')
            ->and($pending->fresh()?->purchase_id)->toBeNull()
            ->and($pending->fresh()?->period_key)->toBe($periodKey)
            ->and($subscription->fresh()?->next_billing_at?->toIso8601String())->toBe($before)
            ->and($subscription->fresh()?->chip_status)->toBe(SubscriptionStatus::Active);

        // One terminal mismatch receipt with the actual charge; null period
        // key avoids stealing the occupied period unique.
        $mismatch = RenewalAttempt::query()->where('purchase_id', 'pur_f04_unrelated')->first();

        expect($mismatch?->status)->toBe('mismatch')
            ->and($mismatch?->amount_minor)->toBe(100)
            ->and($mismatch?->period_key)->toBeNull()
            ->and($mismatch?->last_error_code)->toBe('AMOUNT_MISMATCH')
            ->and($mismatch?->completed_at)->not->toBeNull()
            ->and(RenewalAttempt::query()->where('subscription_id', $subscription->id)->count())->toBe(2);

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->purchaseId === 'pur_f04_unrelated'
            && $event->periodKey === $periodKey
            && $event->expectedAmountMinor === 1000
            && $event->actualAmountMinor === 100
            && $event->expectedCurrency === 'MYR'
            && $event->actualCurrency === 'MYR');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'mismatch')
            && ($context['expected_amount_minor'] ?? null) === 1000
            && ($context['actual_amount_minor'] ?? null) === 100);
    });

    it('preserves a pending claim carrying a distinct purchase id for a mismatched purchase', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();
        $periodKey = ClaimRenewalAttempt::periodKeyFor($subscription);

        $pending = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($pending)->not->toBeNull();

        DB::table($pending->getTable())->where('id', $pending->id)->update(['purchase_id' => 'pur_f04_other']);

        $payload = f04Payload('pur_f04_unrelated2', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        expect($pending->fresh()?->status)->toBe('claimed')
            ->and($pending->fresh()?->purchase_id)->toBe('pur_f04_other')
            ->and($pending->fresh()?->period_key)->toBe($periodKey)
            ->and($subscription->fresh()?->next_billing_at?->toIso8601String())->toBe($before);

        $mismatch = RenewalAttempt::query()->where('purchase_id', 'pur_f04_unrelated2')->first();

        expect($mismatch?->status)->toBe('mismatch')
            ->and($mismatch?->amount_minor)->toBe(100)
            ->and($mismatch?->period_key)->toBeNull()
            ->and(RenewalAttempt::query()->where('subscription_id', $subscription->id)->count())->toBe(2);

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->purchaseId === 'pur_f04_unrelated2'
            && $event->periodKey === $periodKey
            && $event->expectedAmountMinor === 1000
            && $event->actualAmountMinor === 100
            && $event->expectedCurrency === 'MYR'
            && $event->actualCurrency === 'MYR');
    });

    it('stays idempotent when a colliding mismatched purchase is redelivered', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);

        $pending = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($pending)->not->toBeNull();

        $payload = f04Payload('pur_f04_collide_dup', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        expect($pending->fresh()?->status)->toBe('claimed')
            ->and(RenewalAttempt::query()->where('purchase_id', 'pur_f04_collide_dup')->count())->toBe(1)
            ->and(RenewalAttempt::query()->where('purchase_id', 'pur_f04_collide_dup')->first()?->status)->toBe('mismatch')
            ->and(RenewalAttempt::query()->where('purchase_id', 'pur_f04_collide_dup')->first()?->amount_minor)->toBe(100);

        Event::assertDispatchedTimes(SettledPeriodPurchaseConflict::class, 1);
        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->purchaseId === 'pur_f04_collide_dup'
            && $event->expectedAmountMinor === 1000
            && $event->actualAmountMinor === 100);
    });

    it('suppresses the colliding mismatch hook when the outer transaction rolls back', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);

        $pending = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($pending)->not->toBeNull();

        $payload = f04Payload('pur_f04_collide_rb', 100, 'MYR');

        try {
            DB::transaction(function () use ($user, $payload): void {
                SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

                throw new RuntimeException('outer rollback');
            });

            $this->fail('Outer transaction must roll back.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('outer rollback');
        }

        expect(RenewalAttempt::query()->where('purchase_id', 'pur_f04_collide_rb')->count())->toBe(0)
            ->and($pending->fresh()?->status)->toBe('claimed')
            ->and($subscription->fresh()?->next_billing_at?->isPast())->toBeTrue();

        Event::assertNotDispatched(SettledPeriodPurchaseConflict::class);
    });

    it('lets a later correct purchase settle the preserved pending claim', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $before = $subscription->next_billing_at?->toIso8601String();

        $pending = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($pending)->not->toBeNull();

        $badPayload = f04Payload('pur_f04_bad_first', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($badPayload), $badPayload);

        expect(RenewalAttempt::query()->where('purchase_id', 'pur_f04_bad_first')->first()?->status)->toBe('mismatch');

        $goodPayload = f04Payload('pur_f04_good_later', 1000, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($goodPayload), $goodPayload);

        $settled = RenewalAttempt::query()->whereKey($pending->id)->first();

        expect($settled?->status)->toBe('completed')
            ->and($settled?->purchase_id)->toBe('pur_f04_good_later')
            ->and($settled?->amount_minor)->toBe(1000)
            ->and($subscription->fresh()?->next_billing_at?->toIso8601String())->not->toBe($before)
            ->and($subscription->fresh()?->next_billing_at?->isFuture())->toBeTrue()
            ->and(RenewalAttempt::query()->where('purchase_id', 'pur_f04_bad_first')->first()?->status)->toBe('mismatch')
            ->and(RenewalAttempt::query()->where('purchase_id', 'pur_f04_bad_first')->first()?->amount_minor)->toBe(100)
            ->and(RenewalAttempt::query()->where('subscription_id', $subscription->id)->count())->toBe(2);

        Event::assertDispatchedTimes(SettledPeriodPurchaseConflict::class, 1);
    });

    it('does not let a failed receipt swallow a mismatched paid purchase', function (): void {
        Event::fake([SettledPeriodPurchaseConflict::class]);

        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);
        $periodKey = ClaimRenewalAttempt::periodKeyFor($subscription);

        RenewalAttempt::query()->create([
            'subscription_id' => $subscription->id,
            'status' => 'failed',
            'amount_minor' => 1000,
            'period_key' => $periodKey,
            'purchase_id' => 'pur_f04_failed',
            'last_error_code' => 'PAYMENT_DECLINED',
            'completed_at' => CarbonImmutable::now(),
        ]);

        $payload = f04Payload('pur_f04_failed', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $rows = RenewalAttempt::query()->where('purchase_id', 'pur_f04_failed')->orderBy('created_at')->get();

        expect($rows)->toHaveCount(2)
            ->and($rows->first()?->status)->toBe('failed')
            ->and($rows->last()?->status)->toBe('mismatch')
            ->and($rows->last()?->amount_minor)->toBe(100)
            ->and($rows->last()?->period_key)->toBeNull();

        Event::assertDispatched(SettledPeriodPurchaseConflict::class, fn (SettledPeriodPurchaseConflict $event): bool => $event->purchaseId === 'pur_f04_failed'
            && $event->expectedAmountMinor === 1000
            && $event->actualAmountMinor === 100);
    });

    it('leaves mismatched attempts untouched for renewal workers', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_f04']);
        $subscription = f04Subscription($user, 1000);

        $payload = f04Payload('pur_f04_worker', 100, 'MYR');
        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $mismatch = RenewalAttempt::query()->where('purchase_id', 'pur_f04_worker')->first();

        expect($mismatch?->status)->toBe('mismatch');

        // The mismatch occupies the period unique, so no fresh claim can
        // silently restart the period; the host must reconcile first.
        expect(app(ClaimRenewalAttempt::class)->handle($subscription->id))->toBeNull()
            ->and($mismatch->fresh()?->status)->toBe('mismatch')
            ->and(RenewalAttempt::query()->where('subscription_id', $subscription->id)->count())->toBe(1);
    });

    it('scopes mismatch records to the owning tenant', function (): void {
        config()->set('cashier-chip.features.owner.enabled', true);
        config()->set('cashier-chip.features.owner.include_global', false);

        $ownerA = $this->createUser(['chip_id' => 'cli_f04_a']);
        $ownerB = $this->createUser(['chip_id' => 'cli_f04_b']);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

        $subscription = $this->createTrustedSubscription($ownerA, [
            'type' => 'default',
            'chip_id' => 'sub_f04_owner',
            'chip_status' => SubscriptionStatus::Active,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'next_billing_at' => CarbonImmutable::now()->subDay(),
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);
        $this->createTrustedSubscriptionItem($subscription, [
            'chip_id' => 'item_f04_owner',
            'chip_product' => 'prod',
            'chip_price' => 'price_monthly',
            'quantity' => 1,
            'unit_amount' => 1000,
        ]);

        $payload = f04Payload('pur_f04_owner', 100, 'MYR');
        SyncChipPurchaseStatus::run($ownerA, PurchaseData::from($payload), $payload);

        $mismatch = RenewalAttempt::query()->where('purchase_id', 'pur_f04_owner')->first();

        expect($mismatch?->status)->toBe('mismatch')
            ->and($mismatch?->owner_id)->toBe((string) $ownerA->getKey())
            ->and(OwnerContext::withOwner($ownerB, fn (): int => RenewalAttempt::query()->count()))->toBe(0)
            ->and(OwnerContext::withOwner($ownerA, fn (): int => RenewalAttempt::query()->count()))->toBe(1);
    });
});
