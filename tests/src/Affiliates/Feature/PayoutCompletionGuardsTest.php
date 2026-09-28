<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\DisableAffiliate;
use AIArmada\Affiliates\Actions\Affiliates\PauseAffiliate;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Conversions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Actions\Payouts\AssertPayoutCompletable;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function createCompletionGuardAffiliate(string $status = Active::class): Affiliate
{
    return Affiliate::create([
        'code' => 'CGUARD-' . uniqid(),
        'name' => 'Completion Guard Affiliate',
        'status' => $status,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function recordCompletionGuardConversion(Affiliate $affiliate, int $commissionMinor = 5000): AffiliateConversion
{
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'CGUARD-' . uniqid(),
        'value_minor' => $commissionMinor * 10,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    return $conversion;
}

// Blocker 1: conversions reserved by an open payout cannot leave Approved.

test('reversing a conversion reserved by an open payout throws and moves no money', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    expect(fn (): object => ReverseAffiliateConversion::run($conversion, 'duplicate'))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');

    $balance = $affiliate->balanceFor('USD')->fresh();

    expect($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(5000)
        ->and($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($conversion->fresh()->affiliate_payout_id)->toBe((string) $payout->getKey());
});

test('voiding a conversion reserved by an open payout throws', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    CreatePayout::run([$conversion->id]);

    expect(fn (): object => VoidAffiliateConversion::run($conversion, 'fraud'))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');

    expect($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
});

test('rejecting a reserved conversion through the table status action throws', function (): void {
    Gate::before(fn (?object $user): bool => true);

    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    CreatePayout::run([$conversion->id]);

    expect(fn (): bool => AffiliateConversionsTable::updateStatus($conversion, RejectedConversion::class))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');
});

test('marking a reserved conversion paid through the table status action throws', function (): void {
    Gate::before(fn (?object $user): bool => true);

    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    CreatePayout::run([$conversion->id]);

    expect(fn (): bool => AffiliateConversionsTable::updateStatus($conversion, PaidConversion::class))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');
});

test('caller-supplied override metadata is stripped when no override is needed', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    $payout = CreatePayout::run([$conversion->id], [
        'metadata' => ['payout_override' => ['reason' => 'forged'], 'note' => 'kept'],
    ]);

    expect($payout->metadata['payout_override'] ?? null)->toBeNull()
        ->and($payout->metadata['note'] ?? null)->toBe('kept');

    DisableAffiliate::run($affiliate);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');
});

test('a reserved conversion becomes reversible after the payout is cancelled', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    UpdatePayoutStatus::run($payout, 'cancelled');

    ReverseAffiliateConversion::run($conversion->fresh(), 'duplicate');

    $balance = $affiliate->balanceFor('USD')->fresh();

    expect($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0)
        ->and($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue();
});

test('reversing a paid conversion linked to a completed payout still claws back', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    UpdatePayoutStatus::run($payout, 'completed');

    ReverseAffiliateConversion::run($conversion->fresh(), 'chargeback');

    $balance = $affiliate->balanceFor('USD')->fresh();

    expect($balance->available_minor)->toBe(-5000)
        ->and($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue();
});

test('completion refuses when a linked conversion left Approved out of band', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $conversion->getKey())
        ->update(['status' => ReversedConversion::value()]);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'left Approved');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

// Blocker 2: the provider-reconciliation path enforces the same gate.

test('reconcilePayout refuses completion for a disabled affiliate', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    DisableAffiliate::run($affiliate);

    expect(fn (): bool => app(PayoutReconciliationService::class)->reconcilePayout($payout, 'completed', ['reference' => 'ext-1']))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('reconcilePayout refuses completion for a paused affiliate', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    PauseAffiliate::run($affiliate);

    expect(fn (): bool => app(PayoutReconciliationService::class)->reconcilePayout($payout, 'completed', ['reference' => 'ext-1']))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');
});

// Blocker 3: audited disabled-override payouts complete on both paths.

test('an override payout completes through the manual path and stamps the override', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    DisableAffiliate::run($affiliate);

    $payout = CreatePayout::run([$conversion->id], [
        'payout_override_reason' => 'Account closed in good standing.',
    ]);

    $completed = UpdatePayoutStatus::run($payout, 'completed');

    expect($completed->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($completed->metadata['payout_override']['override_completed_at'])->not->toBeNull()
        ->and($completed->events()->where('notes', 'like', '%payout override%')->count())->toBe(1)
        ->and($conversion->fresh()->status->equals(PaidConversion::class))->toBeTrue();
});

test('an override payout completes through the reconciliation path', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    DisableAffiliate::run($affiliate);

    $payout = CreatePayout::run([$conversion->id], [
        'payout_override_reason' => 'Account closed in good standing.',
    ]);

    $changed = app(PayoutReconciliationService::class)->reconcilePayout($payout, 'completed', ['reference' => 'ext-2']);

    expect($changed)->toBeTrue()
        ->and($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($payout->fresh()->metadata['payout_override']['override_completed_at'])->not->toBeNull();
});

test('a forged override on a paused affiliate does not unblock completion', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    PauseAffiliate::run($affiliate);

    DB::table((new AffiliatePayout)->getTable())
        ->where('id', $payout->getKey())
        ->update(['metadata' => json_encode(['payout_override' => ['reason' => 'forged']])]);

    expect(fn (): object => UpdatePayoutStatus::run($payout->fresh(), 'completed'))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');
});

function createBareManualPayout(Affiliate $affiliate, ?array $metadata = null): AffiliatePayout
{
    return AffiliatePayout::create([
        'reference' => 'MANUAL-' . uniqid(),
        'status' => PendingPayout::class,
        'total_minor' => 5000,
        'conversion_count' => 0,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'metadata' => $metadata,
    ]);
}

test('completing a bare manual payout checks payee eligibility', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $payout = createBareManualPayout($affiliate);

    UpdatePayoutStatus::run($payout, 'completed');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue();
});

test('completing a bare manual payout refuses a disabled payee without an override', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $payout = createBareManualPayout($affiliate);

    DisableAffiliate::run($affiliate);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('a stamped override unblocks a bare manual payout for a disabled payee', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $payout = createBareManualPayout($affiliate, ['payout_override' => ['reason' => 'Earned before closure']]);

    DisableAffiliate::run($affiliate);

    UpdatePayoutStatus::run($payout, 'completed');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($payout->fresh()->metadata['payout_override']['override_completed_at'])->not->toBeNull();
});

test('completing a payout that names no affiliate throws', function (): void {
    $payout = AffiliatePayout::create([
        'reference' => 'MANUAL-' . uniqid(),
        'status' => PendingPayout::class,
        'total_minor' => 5000,
        'conversion_count' => 0,
        'currency' => 'USD',
    ]);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'names no affiliate');
});

test('completing a payout linked across affiliates throws', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    $other = createCompletionGuardAffiliate();
    $foreign = recordCompletionGuardConversion($other);

    // Manual linking bypasses CreatePayout's single-affiliate guard.
    $foreign->update(['affiliate_payout_id' => $payout->getKey()]);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'across 2 affiliates');
});

test('the gate resolves eligibility in the payout owner scope', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Gate Owner A',
        'email' => 'gate-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Gate Owner B',
        'email' => 'gate-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $payout = OwnerContext::withOwner($ownerB, function (): AffiliatePayout {
        $affiliate = createCompletionGuardAffiliate();
        $conversion = recordCompletionGuardConversion($affiliate);

        return CreatePayout::run([$conversion->id]);
    });

    // Evaluated under a mismatched ambient owner (cross-owner console
    // work): eligibility still resolves, instead of failing on an
    // ambient-scoped miss.
    $reason = OwnerContext::withOwner($ownerA, fn (): ?string => AssertPayoutCompletable::run($payout));

    expect($reason)->toBeNull();
});

test('completion sync reads conversions in the payout owner scope', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Sync Owner A',
        'email' => 'sync-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Sync Owner B',
        'email' => 'sync-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    [$payout, $conversion] = OwnerContext::withOwner($ownerB, function (): array {
        $affiliate = createCompletionGuardAffiliate();
        $conversion = recordCompletionGuardConversion($affiliate);

        return [CreatePayout::run([$conversion->id]), $conversion];
    });

    $action = app(UpdatePayoutStatus::class);
    $sync = new ReflectionMethod(UpdatePayoutStatus::class, 'syncConversions');
    $sync->setAccessible(true);

    // Evaluated under a mismatched ambient owner: the Approved→Paid
    // flip and the operation sync must still land on the payout's own
    // rows instead of silently matching zero rows (0 === 0 pass).
    OwnerContext::withOwner($ownerA, fn (): mixed => $sync->invoke($action, $payout, new CompletedPayout($payout)));

    expect($conversion->fresh()->status->equals(PaidConversion::class))->toBeTrue()
        ->and($payout->operation()->withoutGlobalScopes()->first()->status)->toBe('completed');
});

test('manual completion refuses mixed-currency linked conversions', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    $eur = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'CGUARD-EUR-' . uniqid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'EUR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($eur);

    // Raw linking bypasses CreatePayout's single-currency guard and
    // the conversion model's saving link guard, the way corrupt data
    // would arrive.
    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $eur->getKey())
        ->update(['affiliate_payout_id' => $payout->getKey()]);

    expect(fn (): object => UpdatePayoutStatus::run($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'same currency');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('the gate fails legibly when the affiliate is missing', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = CreatePayout::run([$conversion->id]);

    DB::table($affiliate->getTable())->where('id', $affiliate->getKey())->delete();

    expect(fn (): ?string => AssertPayoutCompletable::run($payout->fresh()))
        ->toThrow(PayoutCompletionBlockedException::class, 'missing or outside the payout owner scope');
});
