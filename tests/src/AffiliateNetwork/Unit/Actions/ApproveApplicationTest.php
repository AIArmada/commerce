<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

describe('ApproveApplication', function (): void {
    beforeEach(function (): void {
        $this->action = app(ApproveApplication::class);
    });

    test('approves pending application', function (): void {
        Event::fake();

        $application = AffiliateOfferApplication::factory()->pending()->create();

        $approved = $this->action->execute($application, 'admin@example.com');

        expect($approved->status)->toBe(ApplicationStatus::Approved);
        expect($approved->reviewed_by)->toBe('admin@example.com');
        expect($approved->reviewed_at)->not->toBeNull();

        Event::assertDispatched(ApplicationApproved::class);
    });

    test('approves without reviewer', function (): void {
        $application = AffiliateOfferApplication::factory()->pending()->create();

        $approved = $this->action->execute($application);

        expect($approved->status)->toBe(ApplicationStatus::Approved);
        expect($approved->reviewed_by)->toBeNull();
    });

    test('fails when application is no longer accessible', function (): void {
        $application = AffiliateOfferApplication::factory()->pending()->create();

        $application->delete();

        $this->action->execute($application, 'admin@example.com');
    })->throws(ModelNotFoundException::class);
});

test('repeat approval with a stale pending model preserves the first review and emits nothing new', function (): void {
    $application = AffiliateOfferApplication::factory()->pending()->create();
    Event::fake([ApplicationApproved::class]);
    $action = app(ApproveApplication::class);
    $first = $action->execute($application, 'first-reviewer');
    $this->travel(1)->days();
    $second = $action->execute($application, 'second-reviewer');
    expect($second->reviewed_by)->toBe('first-reviewer')
        ->and($second->approved_at->equalTo($first->approved_at))->toBeTrue()
        ->and($second->reviewed_at->equalTo($first->reviewed_at))->toBeTrue()
        ->and($second->updated_at->equalTo($first->updated_at))->toBeTrue();
    Event::assertDispatchedTimes(ApplicationApproved::class, 1);
});

test('manual approval event waits for the enclosing transaction to commit', function (): void {
    $application = AffiliateOfferApplication::factory()->pending()->create();
    Event::fake([ApplicationApproved::class]);
    DB::transaction(function () use ($application): void {
        app(ApproveApplication::class)->execute($application);
        Event::assertNotDispatched(ApplicationApproved::class);
    });
    Event::assertDispatchedTimes(ApplicationApproved::class, 1);
});

test('rolled back manual approval never dispatches approval', function (): void {
    $application = AffiliateOfferApplication::factory()->pending()->create();
    Event::fake([ApplicationApproved::class]);
    DB::beginTransaction();

    try {
        app(ApproveApplication::class)->execute($application);
    } finally {
        DB::rollBack();
    }
    expect($application->fresh()->status)->toBe(ApplicationStatus::Pending);
    Event::assertNotDispatched(ApplicationApproved::class);
});

test('cannot approve another owners application', function (): void {
    config(['affiliate-network.owner.enabled' => true, 'affiliates.owner.enabled' => true]);
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $application = OwnerContext::withOwner($owner, function (): AffiliateOfferApplication {
        $affiliate = createTestAffiliate();

        return AffiliateOfferApplication::factory()->forAffiliateId($affiliate->id)->pending()->create();
    });
    Event::fake([ApplicationApproved::class]);

    expect(fn () => OwnerContext::withOwner($otherOwner, fn () => app(ApproveApplication::class)->execute($application)))->toThrow(ModelNotFoundException::class);
    expect(OwnerContext::withOwner($owner, fn () => $application->fresh()->status))->toBe(ApplicationStatus::Pending);
    Event::assertNotDispatched(ApplicationApproved::class);
});
