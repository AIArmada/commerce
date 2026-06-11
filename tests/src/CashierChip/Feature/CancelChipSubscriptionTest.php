<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Feature;

use AIArmada\CashierChip\Actions\CancelChipSubscription;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\Commerce\Tests\TestCase;
use Illuminate\Support\Facades\Event;

\uses(TestCase::class);
describe('CancelChipSubscription', function (): void {
    it('cancels a subscription and dispatches event', function (): void {
        Event::fake();

        $subscription = Subscription::factory()->create([
            'billable_type' => 'user',
            'billable_id' => '1',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        app(CancelChipSubscription::class)->cancel($subscription);

        $subscription->refresh();

        expect($subscription->chip_status)->toBe(Subscription::STATUS_CANCELED);
        expect($subscription->ends_at)->not->toBeNull();

        Event::assertDispatched(SubscriptionCanceled::class);
    });

    it('marks subscription as past due and dispatches renewal failure', function (): void {
        Event::fake();

        $subscription = Subscription::factory()->create([
            'billable_type' => 'user',
            'billable_id' => '1',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        app(CancelChipSubscription::class)->markPastDue($subscription, 'Card declined');

        $subscription->refresh();

        expect($subscription->chip_status)->toBe(Subscription::STATUS_PAST_DUE);

        Event::assertDispatched(SubscriptionRenewalFailed::class, function (SubscriptionRenewalFailed $event): bool {
            return str_contains($event->reason, 'Card declined');
        });
    });
});
