<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Support\SubscriptionStatus;

it('has all expected status cases', function (): void {
    $cases = SubscriptionStatus::cases();

    expect($cases)->toHaveCount(8)
        ->and(collect($cases)->pluck('value')->toArray())->toContain(
            'active',
            'trialing',
            'past_due',
            'canceled',
            'grace_period',
            'paused',
            'incomplete',
            'expired'
        );
});

it('provides label for each status', function (SubscriptionStatus $status): void {
    expect($status->label())->toBeString()->not->toBeEmpty();
})->with([
    'active' => [SubscriptionStatus::Active],
    'on_trial' => [SubscriptionStatus::OnTrial],
    'past_due' => [SubscriptionStatus::PastDue],
    'canceled' => [SubscriptionStatus::Canceled],
    'on_grace_period' => [SubscriptionStatus::OnGracePeriod],
    'paused' => [SubscriptionStatus::Paused],
    'incomplete' => [SubscriptionStatus::Incomplete],
    'expired' => [SubscriptionStatus::Expired],
]);

it('provides color for each status', function (SubscriptionStatus $status): void {
    expect($status->color())->toBeString()->not->toBeEmpty();
})->with([
    'active' => [SubscriptionStatus::Active],
    'on_trial' => [SubscriptionStatus::OnTrial],
    'past_due' => [SubscriptionStatus::PastDue],
    'canceled' => [SubscriptionStatus::Canceled],
    'on_grace_period' => [SubscriptionStatus::OnGracePeriod],
    'paused' => [SubscriptionStatus::Paused],
    'incomplete' => [SubscriptionStatus::Incomplete],
    'expired' => [SubscriptionStatus::Expired],
]);

it('provides icon for each status', function (SubscriptionStatus $status): void {
    expect($status->icon())->toBeString()->toContain('heroicon');
})->with([
    'active' => [SubscriptionStatus::Active],
    'on_trial' => [SubscriptionStatus::OnTrial],
    'past_due' => [SubscriptionStatus::PastDue],
    'canceled' => [SubscriptionStatus::Canceled],
    'on_grace_period' => [SubscriptionStatus::OnGracePeriod],
    'paused' => [SubscriptionStatus::Paused],
    'incomplete' => [SubscriptionStatus::Incomplete],
    'expired' => [SubscriptionStatus::Expired],
]);

it('returns correct color for active status', function (): void {
    expect(SubscriptionStatus::Active->color())->toBe('success');
});

it('returns correct color for on_trial status', function (): void {
    expect(SubscriptionStatus::OnTrial->color())->toBe('warning');
});

it('returns correct color for past_due status', function (): void {
    expect(SubscriptionStatus::PastDue->color())->toBe('danger');
});

it('returns correct color for canceled status', function (): void {
    expect(SubscriptionStatus::Canceled->color())->toBe('danger');
});

it('returns correct color for on_grace_period status', function (): void {
    expect(SubscriptionStatus::OnGracePeriod->color())->toBe('info');
});

it('returns correct color for paused status', function (): void {
    expect(SubscriptionStatus::Paused->color())->toBe('gray');
});

it('returns correct color for incomplete status', function (): void {
    expect(SubscriptionStatus::Incomplete->color())->toBe('warning');
});

it('returns correct color for expired status', function (): void {
    expect(SubscriptionStatus::Expired->color())->toBe('gray');
});

it('active status is cancelable', function (): void {
    expect(SubscriptionStatus::Active->isCancelable())->toBeTrue();
});

it('paused status is resumable', function (): void {
    expect(SubscriptionStatus::Paused->isResumable())->toBeTrue();
});

it('active status is active', function (): void {
    expect(SubscriptionStatus::Active->isActive())->toBeTrue();
});

it('canceled status is not active', function (): void {
    expect(SubscriptionStatus::Canceled->isActive())->toBeFalse();
});
