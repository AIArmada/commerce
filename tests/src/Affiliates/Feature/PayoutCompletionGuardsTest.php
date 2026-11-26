<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Features\Conversions\Actions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Conversions\Actions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\Actions\VoidAffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycle;
use AIArmada\Affiliates\Features\Payouts\Actions\AssertPayoutCompletable;
use AIArmada\Affiliates\Features\Payouts\Actions\CreatePayout;
use AIArmada\Affiliates\Features\Payouts\Actions\UpdatePayoutStatus;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldAdministration;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
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

    app(ApplyConversionAccounting::class)->handle($conversion);

    return $conversion;
}

// Blocker 1: conversions reserved by an open payout cannot leave Approved.

test('reversing a conversion reserved by an open payout throws and moves no money', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    expect(fn (): object => app(ReverseAffiliateConversion::class)->handle($conversion, 'duplicate'))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');

    $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->fresh();

    expect($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(5000)
        ->and($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($conversion->fresh()->affiliate_payout_id)->toBe((string) $payout->getKey());
});

test('voiding a conversion reserved by an open payout throws', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    app(CreatePayout::class)->handle([$conversion->id]);

    expect(fn (): object => app(VoidAffiliateConversion::class)->handle($conversion, 'fraud'))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');

    expect($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
});

test('rejecting a reserved conversion through the table status action throws', function (): void {
    Gate::before(fn (?object $user): bool => true);

    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    app(CreatePayout::class)->handle([$conversion->id]);

    expect(fn (): bool => AffiliateConversionsTable::updateStatus($conversion, RejectedConversion::class))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');
});

test('marking a reserved conversion paid through the table status action throws', function (): void {
    Gate::before(fn (?object $user): bool => true);

    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    app(CreatePayout::class)->handle([$conversion->id]);

    expect(fn (): bool => AffiliateConversionsTable::updateStatus($conversion, PaidConversion::class))
        ->toThrow(InvalidArgumentException::class, 'reserved by open payout');
});

test('caller-supplied override metadata is stripped when no override is needed', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    $payout = app(CreatePayout::class)->handle([$conversion->id], [
        'metadata' => ['payout_override' => ['reason' => 'forged'], 'note' => 'kept'],
    ]);

    expect($payout->metadata['payout_override'] ?? null)->toBeNull()
        ->and($payout->metadata['note'] ?? null)->toBe('kept');

    app(AffiliateLifecycle::class)->disable($affiliate->id);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');
});

test('a reserved conversion becomes reversible after the payout is cancelled', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    app(UpdatePayoutStatus::class)->handle($payout, 'cancelled');

    app(ReverseAffiliateConversion::class)->handle($conversion->fresh(), 'duplicate');

    $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->fresh();

    expect($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0)
        ->and($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue();
});

test('reversing a paid conversion linked to a completed payout still claws back', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    app(UpdatePayoutStatus::class)->handle($payout, 'completed');

    app(ReverseAffiliateConversion::class)->handle($conversion->fresh(), 'chargeback');

    $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->fresh();

    expect($balance->available_minor)->toBe(-5000)
        ->and($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue();
});

test('completion refuses when a linked conversion left Approved out of band', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $conversion->getKey())
        ->update(['status' => ReversedConversion::value()]);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'left Approved');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

// Blocker 2: the provider-reconciliation path enforces the same gate.

test('reconcilePayout refuses completion for a disabled affiliate', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    app(AffiliateLifecycle::class)->disable($affiliate->id);

    expect(fn (): bool => app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed', ['reference' => 'ext-1']))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('reconcilePayout refuses completion for a paused affiliate', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    app(AffiliateLifecycle::class)->pause($affiliate->id);

    expect(fn (): bool => app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed', ['reference' => 'ext-1']))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');
});

// Blocker 3: audited disabled-override payouts complete on both paths.

test('an override payout completes through the manual path and stamps the override', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    app(AffiliateLifecycle::class)->disable($affiliate->id);

    $payout = app(CreatePayout::class)->handle([$conversion->id], [
        'payout_override_reason' => 'Account closed in good standing.',
    ]);

    $completed = app(UpdatePayoutStatus::class)->handle($payout, 'completed');

    expect($completed->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($completed->metadata['payout_override']['override_completed_at'])->not->toBeNull()
        ->and(AffiliatePayoutEvent::query()->where('affiliate_payout_id', $completed->getKey())->where('notes', 'like', '%payout override%')->count())->toBe(1)
        ->and($conversion->fresh()->status->equals(PaidConversion::class))->toBeTrue();
});

test('an override payout completes through the reconciliation path', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    app(AffiliateLifecycle::class)->disable($affiliate->id);

    $payout = app(CreatePayout::class)->handle([$conversion->id], [
        'payout_override_reason' => 'Account closed in good standing.',
    ]);

    $changed = app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed', ['reference' => 'ext-2']);

    expect($changed)->toBeTrue()
        ->and($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($payout->fresh()->metadata['payout_override']['override_completed_at'])->not->toBeNull();
});

test('a forged override on a paused affiliate does not unblock completion', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    app(AffiliateLifecycle::class)->pause($affiliate->id);

    DB::table((new AffiliatePayout)->getTable())
        ->where('id', $payout->getKey())
        ->update(['metadata' => json_encode(['payout_override' => ['reason' => 'forged']])]);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout->fresh(), 'completed'))
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

    app(UpdatePayoutStatus::class)->handle($payout, 'completed');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue();
});

test('manual completion cannot pay a Dawah-purpose affiliate', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $affiliate->forceFill(['purpose' => 'share_member'])->save();
    $payout = createBareManualPayout($affiliate);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'A non-commercial affiliate payout cannot be completed.');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('completing a bare manual payout refuses a disabled payee without an override', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $payout = createBareManualPayout($affiliate);

    app(AffiliateLifecycle::class)->disable($affiliate->id);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'can no longer receive payouts');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('a stamped override unblocks a bare manual payout for a disabled payee', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $payout = createBareManualPayout($affiliate, ['payout_override' => ['reason' => 'Earned before closure']]);

    app(AffiliateLifecycle::class)->disable($affiliate->id);

    app(UpdatePayoutStatus::class)->handle($payout, 'completed');

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

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'names no affiliate');
});

test('linking a foreign affiliate conversion is rejected before it is persisted', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);
    $other = createCompletionGuardAffiliate();
    $foreign = recordCompletionGuardConversion($other);

    expect(fn (): bool => $foreign->update(['affiliate_payout_id' => $payout->getKey()]))
        ->toThrow(InvalidArgumentException::class, 'across 2 affiliates');

    expect($foreign->fresh()->affiliate_payout_id)->toBeNull()
        ->and(AffiliateBalance::query()->where('affiliate_id', $other->getKey())->where('currency', 'USD')->first()->available_minor)->toBe(5000)
        ->and($payout->fresh()->total_minor)->toBe(5000)
        ->and($payout->conversions()->count())->toBe(1);
});

test('changing the affiliate of a payout-linked conversion is rejected', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    app(CreatePayout::class)->handle([$conversion->id]);
    $other = createCompletionGuardAffiliate();

    expect(fn (): bool => $conversion->fresh()->update(['affiliate_id' => $other->id]))
        ->toThrow(InvalidArgumentException::class, 'across 2 affiliates');

    expect($conversion->fresh()->affiliate_id)->toBe($affiliate->id);
});

test('changing the currency of a payout-linked conversion is rejected', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    app(CreatePayout::class)->handle([$conversion->id]);

    expect(fn (): bool => $conversion->fresh()->update(['commission_currency' => 'EUR']))
        ->toThrow(InvalidArgumentException::class, 'same currency');

    expect($conversion->fresh()->commission_currency)->toBe('USD');
});

test('a record-only payout accepts conversions for its own affiliate', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = createBareManualPayout($affiliate);

    $conversion->update(['affiliate_payout_id' => $payout->getKey()]);

    expect($conversion->fresh()->affiliate_payout_id)->toBe($payout->getKey())
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->available_minor)->toBe(5000);
});

test('a conversion cannot be linked to another owners payout', function (): void {
    config()->set('affiliates.owner.enabled', true);
    $ownerA = User::query()->create(['name' => 'A', 'email' => uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => uniqid() . '@example.com', 'password' => 'secret']);

    $payout = OwnerContext::withOwner($ownerA, function (): AffiliatePayout {
        $affiliate = createCompletionGuardAffiliate();

        return app(CreatePayout::class)->handle([recordCompletionGuardConversion($affiliate)->id]);
    });

    OwnerContext::withOwner($ownerB, function () use ($payout): void {
        $conversion = recordCompletionGuardConversion(createCompletionGuardAffiliate());

        expect(fn (): bool => $conversion->update(['affiliate_payout_id' => $payout->getKey()]))
            ->toThrow(InvalidArgumentException::class, 'missing or outside the owner scope');

        expect($conversion->fresh()->affiliate_payout_id)->toBeNull();
    });
});

test('completing a payout linked across affiliates throws', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    $other = createCompletionGuardAffiliate();
    $foreign = recordCompletionGuardConversion($other);

    // Simulate legacy corruption that bypassed model events.
    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $foreign->getKey())
        ->update(['affiliate_payout_id' => $payout->getKey()]);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'across 2 affiliates');

    expect($payout->fresh()->status->equals(PendingPayout::class))->toBeTrue()
        ->and($foreign->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
});

test('completion refuses extra same-affiliate conversions that were never reserved', function (bool $reconcile): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);
    $extra = recordCompletionGuardConversion($affiliate);
    $extra->update(['affiliate_payout_id' => $payout->id]);

    expect(fn (): mixed => $reconcile
        ? app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed')
        : app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'reservation');

    expect($extra->fresh()->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->available_minor)->toBe(5000);
})->with([false, true]);

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

        return app(CreatePayout::class)->handle([$conversion->id]);
    });

    // Evaluated under a mismatched ambient owner (cross-owner console
    // work): eligibility still resolves, instead of failing on an
    // ambient-scoped miss.
    $reason = OwnerContext::withOwner($ownerA, fn (): ?string => app(AssertPayoutCompletable::class)->handle($payout));

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

        return [app(CreatePayout::class)->handle([$conversion->id]), $conversion];
    });

    $action = app(UpdatePayoutStatus::class);
    $sync = new ReflectionMethod(UpdatePayoutStatus::class, 'syncConversions');
    $sync->setAccessible(true);

    // Evaluated under a mismatched ambient owner: the Approved→Paid
    // flip and the operation sync must still land on the payout's own
    // rows instead of silently matching zero rows (0 === 0 pass).
    $payout->getConnection()->transaction(function () use ($ownerA, $ownerB, $payout, $sync, $action): void {
        OwnerContext::withOwner($ownerB, fn () => $payout->status->transitionTo(CompletedPayout::class));
        OwnerContext::withOwner($ownerA, fn (): mixed => $sync->invoke($action, $payout, new CompletedPayout($payout)));
    });

    expect($conversion->fresh()->status->equals(PaidConversion::class))->toBeTrue()
        ->and(AffiliatePayoutOperation::query()->withoutGlobalScopes()->findOrFail($payout->affiliate_payout_operation_id)->status)->toBe('completed');
});

test('manual completion refuses mixed-currency linked conversions', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

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

    app(ApplyConversionAccounting::class)->handle($eur);

    // Raw linking bypasses CreatePayout's single-currency guard and
    // the conversion model's saving link guard, the way corrupt data
    // would arrive.
    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $eur->getKey())
        ->update(['affiliate_payout_id' => $payout->getKey()]);

    expect(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'same currency');

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
});

test('the gate fails legibly when the affiliate is missing', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    DB::table($affiliate->getTable())->where('id', $affiliate->getKey())->delete();

    expect(fn (): ?string => app(AssertPayoutCompletable::class)->handle($payout->fresh()))
        ->toThrow(PayoutCompletionBlockedException::class, 'missing or outside the payout owner scope');
});

test('a missing reserved balance rolls back payout failure without unlinking conversions', function (bool $reconcile): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->delete();

    expect(fn (): mixed => $reconcile
        ? app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'failed')
        : app(UpdatePayoutStatus::class)->handle($payout, 'failed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'reserved balance is missing');

    expect($conversion->fresh()->affiliate_payout_id)->toBe($payout->id)
        ->and($payout->fresh()->status->getValue())->toBe('pending')
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->funds_released_at)->toBeNull();
})->with([false, true]);

test('payout failure refuses to resurrect a conversion changed outside settlement', function (bool $reconcile): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    $conversion->newQuery()->whereKey($conversion->id)->update(['status' => 'rejected']);

    expect(fn (): mixed => $reconcile
        ? app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'failed')
        : app(UpdatePayoutStatus::class)->handle($payout, 'failed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'no longer approved');

    expect($conversion->fresh()->status->getValue())->toBe('rejected')
        ->and($conversion->fresh()->affiliate_payout_id)->toBe($payout->id)
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->available_minor)->toBe(0);
})->with([false, true]);

test('manual payout reservation refuses duplicate conversion identifiers', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);

    expect(fn (): object => app(CreatePayout::class)->handle([$conversion->id, $conversion->id]))
        ->toThrow(InvalidArgumentException::class, 'unique conversion IDs');

    expect($conversion->fresh()->affiliate_payout_id)->toBeNull()
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->available_minor)->toBe(5000);
});

test('manual payout reservation accepts a normalized conversion currency', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $conversion->newQuery()->whereKey($conversion->id)->update(['commission_currency' => 'usd']);

    $payout = app(CreatePayout::class)->handle([$conversion->id]);

    expect($payout->currency)->toBe('USD')
        ->and($conversion->fresh()->affiliate_payout_id)->toBe($payout->id)
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->available_minor)->toBe(0);
});

test('manual payout creation cannot reserve funds while an active hold exists', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $holds = app(PayoutHoldAdministration::class);
    $holdId = $holds->place($affiliate->id, 'Manual review');

    expect(fn (): object => app(CreatePayout::class)->handle([$conversion->id]))
        ->toThrow(InvalidArgumentException::class, 'active payout hold')
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', 'USD')->first()->fresh()->available_minor)->toBe(5000)
        ->and($conversion->fresh()->affiliate_payout_id)->toBeNull();

    $holds->release($affiliate->id, $holdId);

    expect(app(CreatePayout::class)->handle([$conversion->id])->total_minor)->toBe(5000);
});

test('a hold placed after reservation blocks submission and completion until released', function (): void {
    $affiliate = createCompletionGuardAffiliate();
    $conversion = recordCompletionGuardConversion($affiliate);
    $payout = app(CreatePayout::class)->handle([$conversion->id]);
    $holds = app(PayoutHoldAdministration::class);
    $holdId = $holds->place($affiliate->id, 'Review after reservation');

    expect(fn (): ?string => app(AssertPayoutCompletable::class)->handle($payout, false))
        ->toThrow(PayoutCompletionBlockedException::class, 'active payout hold')
        ->and(fn (): object => app(UpdatePayoutStatus::class)->handle($payout, 'completed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'active payout hold')
        ->and(fn (): bool => app(PayoutReconciliation::class)->reconcile($payout->id, 'completed'))
        ->toThrow(PayoutCompletionBlockedException::class, 'active payout hold')
        ->and($payout->fresh()->status->equals(PendingPayout::class))->toBeTrue()
        ->and($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();

    $holds->release($affiliate->id, $holdId);

    expect(app(UpdatePayoutStatus::class)->handle($payout, 'completed')->status->equals(CompletedPayout::class))->toBeTrue();
});

test('payout settlement rejects hidden conversions with a forged matching affiliate', function (string $status): void {
    config(['affiliates.owner.enabled' => true, 'affiliates.owner.include_global' => false]);
    $ownerA = User::query()->create(['name' => 'Owner A', 'email' => uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Owner B', 'email' => uniqid() . '@example.com', 'password' => 'secret']);

    [$affiliate, $conversion, $payout] = OwnerContext::withOwner($ownerA, function (): array {
        $affiliate = createCompletionGuardAffiliate();
        $conversion = recordCompletionGuardConversion($affiliate);

        return [$affiliate, $conversion, app(CreatePayout::class)->handle([$conversion->id])];
    });

    $foreign = OwnerContext::withOwner($ownerB, fn (): AffiliateConversion => recordCompletionGuardConversion(createCompletionGuardAffiliate()));

    AffiliateConversion::query()->withoutOwnerScope()->whereKey($foreign->id)->update([
        'affiliate_id' => $affiliate->id,
        'affiliate_payout_id' => $payout->id,
    ]);

    OwnerContext::withOwner($ownerA, function () use ($payout, $conversion, $status): void {
        expect(fn () => app(UpdatePayoutStatus::class)->handle($payout, $status))
            ->toThrow(PayoutCompletionBlockedException::class, 'outside its owner scope');

        expect($payout->fresh()->status->equals(PendingPayout::class))->toBeTrue()
            ->and($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue()
            ->and($conversion->fresh()->affiliate_payout_id)->toBe($payout->id);
    });

    OwnerContext::withOwner($ownerB, function () use ($foreign, $payout): void {
        expect($foreign->fresh()->affiliate_payout_id)->toBe($payout->id)
            ->and($foreign->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
    });
})->with(['completed', 'cancelled']);
