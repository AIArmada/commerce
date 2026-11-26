<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Features\Identity\Actions\CreateAffiliate;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lifecycleEventAffiliateAttributes(array $overrides = []): array
{
    return array_merge([
        'code' => 'TIMING-' . mb_strtoupper(bin2hex(random_bytes(4))),
        'name' => 'Lifecycle Timing Affiliate',
        'status' => Draft::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ], $overrides);
}

it('dispatches AffiliateCreated after the outer transaction commits with contact details', function (): void {
    $observedContact = null;

    Event::listen(AffiliateCreated::class, function (AffiliateCreated $event) use (&$observedContact): void {
        $observedContact = Affiliate::query()->find($event->affiliate->id)?->contact_email;
    });

    DB::transaction(function () use (&$observedContact): void {
        Affiliate::create(lifecycleEventAffiliateAttributes([
            'contact_email' => 'created-after-commit@example.com',
        ]));

        expect($observedContact)->toBeNull();
    });

    expect($observedContact)->toBe('created-after-commit@example.com');
});

it('dispatches AffiliateCreated after commit for contacts added after the affiliate save', function (): void {
    $observedContact = null;

    Event::listen(AffiliateCreated::class, function (AffiliateCreated $event) use (&$observedContact): void {
        $observedContact = Affiliate::query()->find($event->affiliate->id)?->contact_email;
    });

    DB::transaction(function () use (&$observedContact): void {
        app(CreateAffiliate::class)->handle([
            'code' => 'TIMING-CREATE-ACTION',
            'name' => 'Timing Create Action',
            'contact_email' => 'action-created@example.com',
        ]);

        expect($observedContact)->toBeNull();
    });

    expect($observedContact)->toBe('action-created@example.com');
});

it('suppresses AffiliateCreated when the outer transaction rolls back', function (): void {
    Event::fake([AffiliateCreated::class]);

    expect(fn () => DB::transaction(function (): void {
        Affiliate::create(lifecycleEventAffiliateAttributes([
            'code' => 'TIMING-ROLLBACK-CREATED',
        ]));

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);

    Event::assertNotDispatched(AffiliateCreated::class);
    expect(Affiliate::query()->where('code', 'TIMING-ROLLBACK-CREATED')->exists())->toBeFalse();
});

it('dispatches AffiliateActivated after the outer transaction commits with updated contact details', function (): void {
    $affiliate = Affiliate::create(lifecycleEventAffiliateAttributes([
        'code' => 'TIMING-ACTIVATED',
        'contact_email' => 'before-activation@example.com',
    ]));

    $observedContact = null;

    Event::listen(AffiliateActivated::class, function (AffiliateActivated $event) use (&$observedContact): void {
        $observedContact = Affiliate::query()->find($event->affiliate->id)?->contact_email;
    });

    DB::transaction(function () use ($affiliate, &$observedContact): void {
        $affiliate->update([
            'status' => Active::class,
            'contact_email' => 'after-activation@example.com',
        ]);

        expect($observedContact)->toBeNull();
    });

    expect($observedContact)->toBe('after-activation@example.com')
        ->and($affiliate->fresh()->status)->toBeInstanceOf(Active::class);
});

it('suppresses AffiliateActivated when the outer transaction rolls back', function (): void {
    $affiliate = Affiliate::create(lifecycleEventAffiliateAttributes([
        'code' => 'TIMING-ROLLBACK-ACTIVATED',
    ]));

    Event::fake([AffiliateActivated::class]);

    expect(fn () => DB::transaction(function () use ($affiliate): void {
        $affiliate->update(['status' => Active::class]);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);

    Event::assertNotDispatched(AffiliateActivated::class);
    expect($affiliate->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('delivers lifecycle references under the owning affiliate scope', function (): void {
    config(['affiliates.owner.enabled' => true]);

    $owner = User::query()->firstOrFail();
    $observedContact = null;

    Event::listen(AffiliateCreated::class, function (AffiliateCreated $event) use (&$observedContact, $owner): void {
        expect($event->affiliate->ownerType)->toBe($owner->getMorphClass())
            ->and($event->affiliate->ownerId)->toBe((string) $owner->getKey());

        $ownerContext = OwnerContext::fromTypeAndIdOrFail($event->affiliate->ownerType, $event->affiliate->ownerId);

        $observedContact = OwnerContext::withOwner($ownerContext, fn (): ?string => Affiliate::query()
            ->forOwner()
            ->find($event->affiliate->id)
            ?->contact_email);
    });

    DB::transaction(function () use ($owner): void {
        OwnerContext::withOwner($owner, fn (): Affiliate => Affiliate::create(lifecycleEventAffiliateAttributes([
            'code' => 'TIMING-OWNED',
            'contact_email' => 'owned-after-commit@example.com',
        ])));
    });

    expect($observedContact)->toBe('owned-after-commit@example.com');
});

it('does not dispatch AffiliateActivated for non-active lifecycle changes', function (): void {
    $affiliate = Affiliate::create(lifecycleEventAffiliateAttributes([
        'code' => 'TIMING-PAUSED',
        'status' => Active::class,
    ]));

    Event::fake([AffiliateActivated::class]);

    DB::transaction(function () use ($affiliate): void {
        $affiliate->update(['status' => Paused::class]);
    });

    Event::assertNotDispatched(AffiliateActivated::class);
});
