<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

describe('ApplyToOffer', function (): void {
    beforeEach(function (): void {
        $this->action = app(ApplyToOffer::class);
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
        $this->affiliate = createTestAffiliate();
    });

    test('creates pending application for offer requiring approval', function (): void {
        Event::fake();

        $this->offer->update(['requires_approval' => true]);

        $application = $this->action->execute($this->offer, (string) $this->affiliate->getKey());

        expect($application)->toBeInstanceOf(AffiliateOfferApplication::class);
        expect($application->offer_id)->toBe($this->offer->id);
        expect($application->affiliate_id)->toBe($this->affiliate->id);
        expect($application->status)->toBe(ApplicationStatus::Pending);

        Event::assertDispatched(ApplicationSubmitted::class);
    });

    test('creates approved application for offer not requiring approval', function (): void {
        $this->offer->update(['requires_approval' => false]);

        $application = $this->action->execute($this->offer, (string) $this->affiliate->getKey());

        expect($application->status)->toBe(ApplicationStatus::Approved);
        expect($application->reviewed_at)->not->toBeNull();
    });

    test('creates approved application when auto_approve enabled', function (): void {
        config(['affiliate-network.applications.auto_approve' => true]);

        $application = $this->action->execute($this->offer, (string) $this->affiliate->getKey());

        expect($application->status)->toBe(ApplicationStatus::Approved);
    });

    test('includes reason in application', function (): void {
        $application = $this->action->execute(
            $this->offer,
            (string) $this->affiliate->getKey(),
            'I have a large audience',
        );

        expect($application->reason)->toBe('I have a large audience');
    });

    test('returns existing application if already exists', function (): void {
        $existing = AffiliateOfferApplication::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->pending()
            ->create();

        Event::fake([ApplicationSubmitted::class, ApplicationApproved::class]);

        $application = $this->action->execute($this->offer, (string) $this->affiliate->getKey());

        expect($application->id)->toBe($existing->id);
        Event::assertNothingDispatched();
    });

    test('allows reapplication after cooldown period', function (): void {
        config(['affiliate-network.applications.cooldown_days' => 7]);

        $existing = AffiliateOfferApplication::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->rejected()
            ->create([
                'rejected_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ]);

        $application = $this->action->execute($this->offer, (string) $this->affiliate->getKey());

        expect($application->id)->toBe($existing->id);
        expect($application->status)->toBe(ApplicationStatus::Pending);
        expect($application->rejection_reason)->toBeNull();
    });

    test('cooldown is measured from rejection not last touch', function (): void {
        config(['affiliate-network.applications.cooldown_days' => 7]);

        $existing = AffiliateOfferApplication::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->rejected()
            ->create([
                'rejected_at' => now()->subDays(10),
                'updated_at' => now(),
            ]);

        $application = $this->action->execute($this->offer, (string) $this->affiliate->getKey());

        expect($application->id)->toBe($existing->id);
        expect($application->status)->toBe(ApplicationStatus::Pending);
    });

    test('throws exception when reapplying before cooldown', function (): void {
        config(['affiliate-network.applications.cooldown_days' => 7]);

        AffiliateOfferApplication::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->rejected()
            ->create([
                'rejected_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);

        $this->action->execute($this->offer, (string) $this->affiliate->getKey());
    })->throws(RuntimeException::class);
});

test('instant approval emits submitted then approved for either trigger', function (bool $requiresApproval, bool $autoApprove): void {
    $site = AffiliateSite::factory()->verified()->create();
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(['requires_approval' => $requiresApproval]);
    $affiliate = createTestAffiliate();
    config(['affiliate-network.applications.auto_approve' => $autoApprove, 'affiliate-network.notifications.enabled' => false]);
    $events = [];
    Event::listen([ApplicationSubmitted::class, ApplicationApproved::class], function (object $event) use (&$events): void {
        $events[] = $event;
    });
    $application = app(ApplyToOffer::class)->execute($offer, $affiliate->id);
    expect(array_map(fn (object $event): string => $event::class, $events))->toBe([
        ApplicationSubmitted::class, ApplicationApproved::class,
    ])->and($events[1]->application->id)->toBe($application->id)
        ->and($events[1]->application->status)->toBe(ApplicationStatus::Approved);
})->with([[false, false], [true, true]]);

test('pending applications and cooldown reapplications never emit approval', function (): void {
    $site = AffiliateSite::factory()->verified()->create();
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(['requires_approval' => true]);
    $affiliate = createTestAffiliate();
    config(['affiliate-network.applications.auto_approve' => false]);
    Event::fake([ApplicationSubmitted::class, ApplicationApproved::class]);
    $application = app(ApplyToOffer::class)->execute($offer, $affiliate->id);
    Event::assertDispatchedTimes(ApplicationSubmitted::class, 1);
    Event::assertNotDispatched(ApplicationApproved::class);
    $application->update(['status' => ApplicationStatus::Rejected, 'rejected_at' => now()->subDays(10)]);
    config(['affiliate-network.applications.auto_approve' => true]);
    $reapplied = app(ApplyToOffer::class)->execute($offer, $affiliate->id);
    expect($reapplied->status)->toBe(ApplicationStatus::Pending);
    Event::assertDispatchedTimes(ApplicationSubmitted::class, 2);
    Event::assertNotDispatched(ApplicationApproved::class);
});

test('applying again to an approved application emits no events', function (): void {
    $offer = AffiliateOffer::factory()->published()->forSite(AffiliateSite::factory()->verified()->create())->create(['requires_approval' => false]);
    $affiliate = createTestAffiliate();
    $action = app(ApplyToOffer::class);
    $existing = $action->execute($offer, $affiliate->id);
    Event::fake([ApplicationSubmitted::class, ApplicationApproved::class]);
    expect($action->execute($offer, $affiliate->id)->id)->toBe($existing->id);
    Event::assertNothingDispatched();
});

test('automatic approval waits for commit and is discarded on rollback', function (bool $commit): void {
    $offer = AffiliateOffer::factory()->published()->forSite(AffiliateSite::factory()->verified()->create())->create(['requires_approval' => false]);
    $affiliate = createTestAffiliate();
    Event::fake([ApplicationSubmitted::class, ApplicationApproved::class]);

    DB::beginTransaction();

    try {
        $application = app(ApplyToOffer::class)->execute($offer, $affiliate->id);
        Event::assertDispatchedTimes(ApplicationSubmitted::class, 1);
        Event::assertNotDispatched(ApplicationApproved::class);
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    if ($commit) {
        DB::commit();
        Event::assertDispatchedTimes(ApplicationApproved::class, 1);
        expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);
    } else {
        DB::rollBack();
        Event::assertNotDispatched(ApplicationApproved::class);
        expect(AffiliateOfferApplication::query()->find($application->id))->toBeNull();
    }
})->with([true, false]);

test('submission listener failure rolls back automatic approval', function (): void {
    $offer = AffiliateOffer::factory()->published()->forSite(AffiliateSite::factory()->verified()->create())->create(['requires_approval' => false]);
    $affiliate = createTestAffiliate();
    Event::fake([ApplicationApproved::class]);
    Event::listen(ApplicationSubmitted::class, function (): void {
        throw new RuntimeException('Submission listener failed');
    });

    expect(fn () => app(ApplyToOffer::class)->execute($offer, $affiliate->id))->toThrow(RuntimeException::class, 'Submission listener failed');
    expect(AffiliateOfferApplication::query()->where('offer_id', $offer->id)->where('affiliate_id', $affiliate->id)->exists())->toBeFalse();
    Event::assertNotDispatched(ApplicationApproved::class);
});
