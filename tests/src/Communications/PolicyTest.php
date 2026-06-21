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

    $communication = Communication::create([
        'direction' => CommunicationDirection::Outbound,
        'category' => CommunicationCategory::Transactional,
        'priority' => CommunicationPriority::Normal,
        'purpose' => 'policy-test',
        'status' => CommunicationStatus::Draft,
    ]);

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
        return Communication::create([
            'direction' => CommunicationDirection::Outbound,
            'category' => CommunicationCategory::Transactional,
            'priority' => CommunicationPriority::Normal,
            'purpose' => 'other-owner',
            'status' => CommunicationStatus::Draft,
        ]);
    });

    expect($policy->view($owner, $communication))->toBeFalse();
});
