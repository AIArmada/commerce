<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\CreateAffiliatePayout;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.owner.auto_assign_on_create', true);
    Gate::before(static fn (?object $user): bool => true);
});

function payoutPageOwner(string $suffix): User
{
    $owner = User::create([
        'name' => 'Payout Owner ' . $suffix,
        'email' => 'payout-owner-' . $suffix . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'affiliate.payout',
            'guard_name' => 'web',
        ]);

        $owner->givePermissionTo($permission);
    });

    return $owner;
}

/** @return array{Affiliate, AffiliateConversion} */
function payoutPageAffiliateAndConversion(
    User $owner,
    string $suffix,
    int $commissionMinor = 5500,
    string $status = Active::class,
    string $purpose = 'general',
): array {
    return OwnerContext::withOwner($owner, function () use ($suffix, $commissionMinor, $status, $purpose): array {
        $affiliate = Affiliate::create([
            'code' => 'AFF-' . Str::upper($suffix) . '-' . Str::uuid(),
            'name' => 'Payout Affiliate ' . $suffix,
            'status' => $status,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
            'purpose' => $purpose,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'external_reference' => 'ORDER-' . Str::upper($suffix) . '-' . Str::uuid(),
            'value_minor' => $commissionMinor * 10,
            'commission_minor' => $commissionMinor,
            'commission_currency' => 'USD',
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        ApplyConversionAccounting::run($conversion);

        return [$affiliate, $conversion];
    });
}

function createPayoutThroughPage(
    User $owner,
    Affiliate $affiliate,
    AffiliateConversion $conversion,
    array $extra = [],
): mixed {
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    return OwnerContext::withOwner($owner, fn (): mixed => Livewire::actingAs($owner)
        ->test(CreateAffiliatePayout::class)
        ->assertOk()
        ->fillForm(array_merge([
            'affiliate_id' => (string) $affiliate->getKey(),
            'conversion_ids' => [(string) $conversion->getKey()],
            'notes' => 'Quarterly payout',
        ], $extra))
        ->call('create'));
}

function invokePayoutPageCreation(array $data): AffiliatePayout
{
    $page = new CreateAffiliatePayout;
    $method = new ReflectionMethod(CreateAffiliatePayout::class, 'handleRecordCreation');
    $method->setAccessible(true);

    $payout = $method->invoke($page, $data);

    expect($payout)->toBeInstanceOf(AffiliatePayout::class);

    return $payout;
}

it('creates through the page by reserving selected conversions and prevents paying them twice', function (): void {
    $owner = payoutPageOwner('success');
    [$affiliate, $conversion] = payoutPageAffiliateAndConversion($owner, 'success');
    $options = OwnerContext::withOwner($owner, fn (): array => CreateAffiliatePayout::conversionOptions(
        (string) $affiliate->getKey(),
        (string) $conversion->external_reference,
    ));

    expect($options[(string) $conversion->getKey()])
        ->toContain((string) $conversion->external_reference, '55.00', 'USD');

    createPayoutThroughPage($owner, $affiliate, $conversion)->assertHasNoFormErrors();

    $payout = OwnerContext::withOwner($owner, fn (): AffiliatePayout => AffiliatePayout::query()
        ->where('payee_id', $affiliate->getKey())
        ->firstOrFail());

    expect($payout->total_minor)->toBe(5500)
        ->and($payout->currency)->toBe('USD')
        ->and($payout->payee_id)->toBe((string) $affiliate->getKey())
        ->and($payout->payee_type)->toBe($affiliate->getMorphClass())
        ->and($payout->conversion_count)->toBe(1)
        ->and($payout->status->equals(PendingPayout::class))->toBeTrue()
        ->and($payout->metadata['notes'])->toBe('Quarterly payout');

    $balance = OwnerContext::withOwner($owner, fn (): ?AffiliateBalance => $affiliate->balanceFor('USD'));

    expect($balance?->available_minor)->toBe(0)
        ->and($conversion->fresh()->affiliate_payout_id)->toBe((string) $payout->getKey());

    OwnerContext::withOwner($owner, fn (): AffiliatePayout => UpdatePayoutStatus::run($payout, 'completed'));

    $completedConversion = OwnerContext::withOwner($owner, fn (): AffiliateConversion => AffiliateConversion::query()->findOrFail($conversion->getKey()));
    $balance = OwnerContext::withOwner($owner, fn (): ?AffiliateBalance => $affiliate->balanceFor('USD'));

    expect($completedConversion->status->equals(PaidConversion::class))->toBeTrue()
        ->and($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($balance?->available_minor)->toBe(0);

    createPayoutThroughPage($owner, $affiliate, $conversion)
        ->assertHasFormErrors()
        ->assertHasNoFormErrors(['affiliate_id']);

    $payoutCount = OwnerContext::withOwner($owner, fn (): int => AffiliatePayout::query()->count());

    expect($payoutCount)->toBe(1);
});

it('uses core payout status rules for disabled affiliate overrides', function (): void {
    $owner = payoutPageOwner('disabled');
    [$affiliate, $conversion] = payoutPageAffiliateAndConversion($owner, 'disabled', status: Disabled::class);

    createPayoutThroughPage($owner, $affiliate, $conversion, [
        'payout_override_reason' => 'Earned before closure',
    ])->assertHasNoFormErrors();

    $payout = OwnerContext::withOwner($owner, fn (): AffiliatePayout => AffiliatePayout::query()
        ->where('payee_id', $affiliate->getKey())
        ->firstOrFail());

    expect($payout->metadata['payout_override']['reason'])->toBe('Earned before closure')
        ->and($payout->metadata['payout_override']['affiliate_status'])->toBe('disabled')
        ->and($payout->metadata['payout_override']['overridden_at'])->not->toBeNull();
});

it('releases reservations once when a page-created payout fails or is cancelled', function (string $status): void {
    $owner = payoutPageOwner($status);
    [$affiliate, $conversion] = payoutPageAffiliateAndConversion($owner, $status);

    createPayoutThroughPage($owner, $affiliate, $conversion)->assertHasNoFormErrors();

    $payout = OwnerContext::withOwner($owner, fn (): AffiliatePayout => AffiliatePayout::query()
        ->where('payee_id', $affiliate->getKey())
        ->firstOrFail());

    OwnerContext::withOwner($owner, fn (): AffiliatePayout => UpdatePayoutStatus::run($payout, $status));
    OwnerContext::withOwner($owner, fn (): AffiliatePayout => UpdatePayoutStatus::run($payout, $status));

    $freshConversion = OwnerContext::withOwner($owner, fn (): AffiliateConversion => AffiliateConversion::query()->findOrFail($conversion->getKey()));
    $balance = OwnerContext::withOwner($owner, fn (): ?AffiliateBalance => $affiliate->balanceFor('USD'));

    expect($payout->fresh()->status->getValue())->toBe($status)
        ->and($freshConversion->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($freshConversion->affiliate_payout_id)->toBeNull()
        ->and($balance?->available_minor)->toBe(5500);
})->with([
    'failed' => FailedPayout::value(),
    'cancelled' => CancelledPayout::value(),
]);

it('rejects a conversion from a different affiliate even when the submitted affiliate is valid', function (): void {
    $owner = payoutPageOwner('wrong-affiliate');
    [$selectedAffiliate] = payoutPageAffiliateAndConversion($owner, 'selected-affiliate');
    [$foreignAffiliate, $foreignConversion] = payoutPageAffiliateAndConversion($owner, 'wrong-affiliate');

    OwnerContext::withOwner($owner, function () use ($selectedAffiliate, $foreignConversion): void {
        expect(fn (): AffiliatePayout => invokePayoutPageCreation([
            'affiliate_id' => (string) $selectedAffiliate->getKey(),
            'conversion_ids' => [(string) $foreignConversion->getKey()],
        ]))->toThrow(ValidationException::class, 'must belong to the selected affiliate');
    });

    expect(OwnerContext::withOwner($owner, fn (): int => AffiliatePayout::query()->count()))->toBe(0)
        ->and(OwnerContext::withOwner($owner, fn (): ?AffiliateBalance => $foreignAffiliate->balanceFor('USD'))?->available_minor)->toBe(5500);
});

it('rejects a foreign owner conversion at the page server boundary', function (): void {
    $ownerA = payoutPageOwner('tenant-a');
    $ownerB = payoutPageOwner('tenant-b');
    [$affiliateA] = payoutPageAffiliateAndConversion($ownerA, 'tenant-a-affiliate');
    [$affiliateB, $conversionB] = payoutPageAffiliateAndConversion($ownerB, 'tenant-b-affiliate');

    OwnerContext::withOwner($ownerA, function () use ($affiliateA, $conversionB): void {
        expect(fn (): AffiliatePayout => invokePayoutPageCreation([
            'affiliate_id' => (string) $affiliateA->getKey(),
            'conversion_ids' => [(string) $conversionB->getKey()],
        ]))->toThrow(ValidationException::class, 'not accessible in the current owner scope');
    });

    expect(OwnerContext::withOwner($ownerB, fn (): int => AffiliatePayout::query()->count()))->toBe(0)
        ->and(OwnerContext::withOwner($ownerB, fn (): ?AffiliateBalance => $affiliateB->balanceFor('USD'))?->available_minor)->toBe(5500);
});

it('ignores injected payout total currency status and payee values', function (): void {
    $owner = payoutPageOwner('injected-attributes');
    [$affiliate, $conversion] = payoutPageAffiliateAndConversion($owner, 'injected-attributes');

    $payout = OwnerContext::withOwner($owner, fn (): AffiliatePayout => invokePayoutPageCreation([
        'affiliate_id' => (string) $affiliate->getKey(),
        'conversion_ids' => [(string) $conversion->getKey()],
        'total_minor' => 1,
        'currency' => 'EUR',
        'status' => CompletedPayout::class,
        'payee_type' => User::class,
        'payee_id' => (string) Str::uuid(),
    ]));

    expect($payout->total_minor)->toBe(5500)
        ->and($payout->currency)->toBe('USD')
        ->and($payout->status->equals(PendingPayout::class))->toBeTrue()
        ->and($payout->payee_type)->toBe($affiliate->getMorphClass())
        ->and($payout->payee_id)->toBe((string) $affiliate->getKey());
});

it('keeps the payout create boundary commercial-purpose only', function (): void {
    $owner = payoutPageOwner('purpose-gate');
    [$affiliate, $conversion] = payoutPageAffiliateAndConversion(
        $owner,
        'purpose-gate',
        purpose: 'share_member',
    );

    OwnerContext::withOwner($owner, function () use ($affiliate, $conversion): void {
        expect(fn (): AffiliatePayout => invokePayoutPageCreation([
            'affiliate_id' => (string) $affiliate->getKey(),
            'conversion_ids' => [(string) $conversion->getKey()],
        ]))->toThrow(ValidationException::class, 'Only commercial-purpose affiliates');
    });

    expect(OwnerContext::withOwner($owner, fn (): int => AffiliatePayout::query()->count()))->toBe(0);
});
