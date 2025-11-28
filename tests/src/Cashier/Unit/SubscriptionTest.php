<?php

declare(strict_types=1);

use AIArmada\Cashier\Models\Subscription;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;
use Carbon\Carbon;

uses(CashierTestCase::class);

describe('Subscription Model', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();
    });

    describe('creation', function (): void {
        it('can create a subscription', function (): void {
            $subscription = $this->createSubscription($this->user);

            expect($subscription)->toBeInstanceOf(Subscription::class)
                ->and($subscription->gateway)->toBe('stripe')
                ->and($subscription->type)->toBe('default');
        });

        it('can create a subscription with specific gateway', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway' => 'chip',
            ]);

            expect($subscription->gateway)->toBe('chip');
        });

        it('belongs to an owner', function (): void {
            $subscription = $this->createSubscription($this->user);

            expect($subscription->owner)->toBeInstanceOf(User::class)
                ->and($subscription->owner->id)->toBe($this->user->id);
        });

        it('has subscription items', function (): void {
            $subscription = $this->createSubscription($this->user);
            $this->createSubscriptionItem($subscription);

            expect($subscription->items)->toHaveCount(1);
        });
    });

    describe('status checks', function (): void {
        it('can check if active', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => null,
            ]);

            expect($subscription->active())->toBeTrue();
        });

        it('can check if canceled', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'ends_at' => Carbon::now()->addDays(7),
            ]);

            expect($subscription->canceled())->toBeTrue();
        });

        it('can check if on trial', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            expect($subscription->onTrial())->toBeTrue();
        });

        it('can check if on grace period', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'ends_at' => Carbon::now()->addDays(5),
            ]);

            expect($subscription->onGracePeriod())->toBeTrue();
        });

        it('can check if ended', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'ends_at' => Carbon::now()->subDays(1),
            ]);

            expect($subscription->ended())->toBeTrue();
        });

        it('can check if incomplete', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_INCOMPLETE,
            ]);

            expect($subscription->incomplete())->toBeTrue();
        });

        it('can check if past due', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_PAST_DUE,
            ]);

            expect($subscription->pastDue())->toBeTrue();
        });

        it('can check if valid', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => null,
            ]);

            expect($subscription->valid())->toBeTrue();
        });

        it('can check if recurring', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'ends_at' => null,
            ]);

            expect($subscription->recurring())->toBeTrue();
        });
    });

    describe('trial management', function (): void {
        it('can skip trial', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            $subscription->skipTrial();

            expect($subscription->trial_ends_at)->toBeNull();
        });

        it('can end trial', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            $subscription->endTrial();
            $subscription->refresh();

            expect($subscription->trial_ends_at)->toBeNull();
        });

        it('can extend trial', function (): void {
            $newTrialEnd = Carbon::now()->addDays(30);
            $subscription = $this->createSubscription($this->user, [
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            $subscription->extendTrial($newTrialEnd);
            $subscription->refresh();

            expect($subscription->trial_ends_at->toDateString())->toBe($newTrialEnd->toDateString());
        });

        it('throws exception when extending trial with past date', function (): void {
            $subscription = $this->createSubscription($this->user);

            $subscription->extendTrial(Carbon::now()->subDays(1));
        })->throws(InvalidArgumentException::class);
    });

    describe('cancellation', function (): void {
        it('can cancel at period end', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'trial_ends_at' => Carbon::now()->addDays(7),
            ]);

            $subscription->cancel();
            $subscription->refresh();

            expect($subscription->ends_at)->not->toBeNull()
                ->and($subscription->canceled())->toBeTrue();
        });

        it('can cancel immediately', function (): void {
            $subscription = $this->createSubscription($this->user);

            $subscription->cancelNow();
            $subscription->refresh();

            expect($subscription->gateway_status)->toBe(Subscription::STATUS_CANCELED)
                ->and($subscription->ends_at)->not->toBeNull();
        });

        it('can cancel at specific date', function (): void {
            $endsAt = Carbon::now()->addDays(10);
            $subscription = $this->createSubscription($this->user);

            $subscription->cancelAt($endsAt);
            $subscription->refresh();

            expect($subscription->ends_at->toDateString())->toBe($endsAt->toDateString());
        });
    });

    describe('resume', function (): void {
        it('can resume a canceled subscription on grace period', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => Carbon::now()->addDays(5),
            ]);

            $subscription->resume();
            $subscription->refresh();

            expect($subscription->ends_at)->toBeNull()
                ->and($subscription->gateway_status)->toBe(Subscription::STATUS_ACTIVE);
        });

        it('throws exception when resuming subscription not on grace period', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'ends_at' => Carbon::now()->subDays(1),
            ]);

            $subscription->resume();
        })->throws(LogicException::class);
    });

    describe('scopes', function (): void {
        it('can query active subscriptions', function (): void {
            $this->createSubscription($this->user, [
                'gateway_status' => Subscription::STATUS_ACTIVE,
                'ends_at' => null,
            ]);
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'gateway_status' => Subscription::STATUS_CANCELED,
                'ends_at' => Carbon::now()->subDay(),
            ]);

            $active = Subscription::query()->active()->get();

            expect($active)->toHaveCount(1);
        });

        it('can query by gateway', function (): void {
            $this->createSubscription($this->user, ['gateway' => 'stripe']);
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'gateway' => 'chip',
            ]);

            $stripeSubscriptions = Subscription::query()->forGateway('stripe')->get();

            expect($stripeSubscriptions)->toHaveCount(1)
                ->and($stripeSubscriptions->first()->gateway)->toBe('stripe');
        });

        it('can query canceled subscriptions', function (): void {
            $this->createSubscription($this->user, [
                'ends_at' => null,
            ]);
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'ends_at' => Carbon::now()->addDays(5),
            ]);

            $canceled = Subscription::query()->canceled()->get();

            expect($canceled)->toHaveCount(1);
        });

        it('can query on trial subscriptions', function (): void {
            $this->createSubscription($this->user, [
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);
            $this->createSubscription($this->user, [
                'type' => 'premium',
                'trial_ends_at' => null,
            ]);

            $onTrial = Subscription::query()->onTrial()->get();

            expect($onTrial)->toHaveCount(1);
        });
    });

    describe('prices', function (): void {
        it('can check if it has a specific price', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_price' => 'price_monthly',
            ]);

            expect($subscription->hasPrice('price_monthly'))->toBeTrue()
                ->and($subscription->hasPrice('price_yearly'))->toBeFalse();
        });

        it('can check if it has a single price', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_price' => 'price_monthly',
            ]);

            expect($subscription->hasSinglePrice())->toBeTrue();
        });

        it('can check if it has multiple prices', function (): void {
            $subscription = $this->createSubscription($this->user, [
                'gateway_price' => null,
            ]);

            expect($subscription->hasMultiplePrices())->toBeTrue();
        });
    });
});
