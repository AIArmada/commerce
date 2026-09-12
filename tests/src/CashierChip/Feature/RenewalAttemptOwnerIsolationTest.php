<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Console\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', true);

    Model::clearBootedModels();
});

function makeCashierChipRenewalOwner(string $label): User
{
    return User::query()->create([
        'name' => $label,
        'email' => Str::uuid()->toString() . '@example.com',
    ]);
}

function makeCashierChipDueSubscription(User $owner): Subscription
{
    return OwnerContext::withOwner($owner, function () use ($owner): Subscription {
        $subscription = Subscription::factory()->for($owner, 'billable')->create([
            'next_billing_at' => CarbonImmutable::now()->subDay(),
        ]);

        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        return $subscription;
    });
}

it('scopes renewal attempts and assigns the subscription owner', function (): void {
    $ownerA = makeCashierChipRenewalOwner('Renewal Owner A');
    $ownerB = makeCashierChipRenewalOwner('Renewal Owner B');
    $subscriptionA = makeCashierChipDueSubscription($ownerA);
    $subscriptionB = makeCashierChipDueSubscription($ownerB);

    $attemptA = OwnerContext::withOwner($ownerA, fn (): ?RenewalAttempt => app(ClaimRenewalAttempt::class)->handle($subscriptionA->id));
    $attemptB = OwnerContext::withOwner($ownerB, fn (): ?RenewalAttempt => app(ClaimRenewalAttempt::class)->handle($subscriptionB->id));

    expect($attemptA)->toBeInstanceOf(RenewalAttempt::class)
        ->and($attemptB)->toBeInstanceOf(RenewalAttempt::class)
        ->and($attemptA?->owner_type)->toBe($ownerA->getMorphClass())
        ->and($attemptA?->owner_id)->toBe($ownerA->getKey())
        ->and($attemptB?->owner_type)->toBe($ownerB->getMorphClass())
        ->and($attemptB?->owner_id)->toBe($ownerB->getKey())
        ->and(OwnerContext::withOwner($ownerA, fn (): int => RenewalAttempt::query()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => RenewalAttempt::query()->count()))->toBe(1);
});

it('blocks cross-owner renewal attempt creation and processing', function (): void {
    $ownerA = makeCashierChipRenewalOwner('Renewal Guard Owner A');
    $ownerB = makeCashierChipRenewalOwner('Renewal Guard Owner B');
    $subscriptionB = makeCashierChipDueSubscription($ownerB);

    expect(fn (): RenewalAttempt => OwnerContext::withOwner($ownerA, fn (): RenewalAttempt => RenewalAttempt::create([
        'subscription_id' => $subscriptionB->id,
        'status' => 'claimed',
        'amount_minor' => 1000,
        'period_key' => '2026-09',
        'lease_expires_at' => CarbonImmutable::now()->addHour(),
    ])))->toThrow(AuthorizationException::class);

    $attemptB = OwnerContext::withOwner($ownerB, fn (): RenewalAttempt => RenewalAttempt::create([
        'subscription_id' => $subscriptionB->id,
        'status' => 'claimed',
        'amount_minor' => 1000,
        'period_key' => '2026-09',
        'lease_expires_at' => CarbonImmutable::now()->addHour(),
    ]));
    $command = app(RenewSubscriptionsCommand::class);
    $method = new ReflectionMethod($command, 'executeAttempt');

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => $method->invoke($command, $attemptB)))
        ->toThrow(AuthorizationException::class);
});

it('backfills legacy renewal attempts from their subscriptions', function (): void {
    $owner = makeCashierChipRenewalOwner('Renewal Backfill Owner');
    $subscription = makeCashierChipDueSubscription($owner);
    $attemptsTable = (new RenewalAttempt)->getTable();
    $attemptId = Str::uuid()->toString();
    $now = CarbonImmutable::now();

    expect(Schema::hasColumns($attemptsTable, ['owner_type', 'owner_id']))->toBeTrue()
        ->and(Schema::hasIndex($attemptsTable, ['owner_type', 'owner_id']))->toBeTrue();

    DB::table($attemptsTable)->insert([
        'id' => $attemptId,
        'subscription_id' => $subscription->id,
        'status' => 'claimed',
        'amount_minor' => 1000,
        'period_key' => '2026-09',
        'purchase_id' => null,
        'last_error_code' => null,
        'lease_expires_at' => $now->addHour(),
        'completed_at' => null,
        'owner_type' => null,
        'owner_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    /** @var object{up: Closure(): void} $migration */
    $migration = require dirname(__DIR__, 4) . '/packages/cashier-chip/database/migrations/2026_09_13_000003_backfill_chip_renewal_attempt_owners.php';

    OwnerContext::withOwner(null, function () use ($migration): void {
        $migration->up();
        $migration->up();
    });

    $attempt = DB::table($attemptsTable)->where('id', $attemptId)->first();

    expect($attempt?->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $attempt?->owner_id)->toBe((string) $owner->getKey());
});
