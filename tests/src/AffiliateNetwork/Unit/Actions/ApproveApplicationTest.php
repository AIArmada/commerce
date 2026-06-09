<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;

describe('ApproveApplication', function (): void {
    beforeEach(function (): void {
        $this->action = app(ApproveApplication::class);
    });

    test('approves pending application', function (): void {
        Event::fake();

        $application = AffiliateOfferApplication::factory()->pending()->create();

        $approved = $this->action->execute($application, 'admin@example.com');

        expect($approved->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
        expect($approved->reviewed_by)->toBe('admin@example.com');
        expect($approved->reviewed_at)->not->toBeNull();

        Event::assertDispatched(ApplicationApproved::class);
    });

    test('dispatches ApplicationApproved event', function (): void {
        Event::fake();

        $application = AffiliateOfferApplication::factory()->pending()->create();

        $this->action->execute($application);

        Event::assertDispatched(ApplicationApproved::class);
    });

    test('approves without reviewer', function (): void {
        $application = AffiliateOfferApplication::factory()->pending()->create();

        $approved = $this->action->execute($application);

        expect($approved->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
        expect($approved->reviewed_by)->toBeNull();
    });

    test('fails when application is no longer accessible', function (): void {
        $application = AffiliateOfferApplication::factory()->pending()->create();

        $application->delete();

        $this->action->execute($application, 'admin@example.com');
    })->throws(ModelNotFoundException::class);
});
