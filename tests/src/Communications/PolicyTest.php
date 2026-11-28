<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Policies\CommunicationPolicy;

test('policy allows view for same owner', function (): void {
    $owner = OwnerContext::resolve();
    $policy = app(CommunicationPolicy::class);

    $communication = (new Communication)->forceFill([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'policy-test',
        'status' => CommunicationStatus::Draft,
    ]);
    $communication->save();

    expect($policy->view($owner, $communication))->toBeTrue();
});

test('policy denies view for different owner', function (): void {
    $policy = app(CommunicationPolicy::class);
    $owner = OwnerContext::resolve();

    $otherOwner = User::create([
        'name' => 'Other User',
        'email' => 'other-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $communication = OwnerContext::withOwner($otherOwner, function () {
        $otherCommunication = (new Communication)->forceFill([
            'direction' => CommunicationDirection::Outbound,
            'category' => CommunicationCategory::Transactional,
            'priority' => CommunicationPriority::Normal,
            'purpose' => 'other-owner',
            'status' => CommunicationStatus::Draft,
        ]);
        $otherCommunication->save();

        return $otherCommunication;
    });

    expect($policy->view($owner, $communication))->toBeFalse();
});

test('global communications are read only in tenant context', function (): void {
    config()->set('communications.features.owner.include_global', true);

    $owner = OwnerContext::resolve();
    $policy = app(CommunicationPolicy::class);
    $communication = new Communication;

    expect($policy->view($owner, $communication))->toBeTrue()
        ->and($policy->retry($owner, $communication))->toBeFalse()
        ->and($policy->cancel($owner, $communication))->toBeFalse()
        ->and(OwnerContext::withOwner(null, fn (): bool => $policy->retry($owner, $communication)))->toBeTrue();
});
