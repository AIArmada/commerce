<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Policies\EventPolicy;

it('allows only the resolved owner to mutate an event by default', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($otherUser, $owner): void {
        $event = Event::factory()->create();
        $policy = new EventPolicy;

        expect($policy->view($owner, $event))->toBeTrue()
            ->and($policy->update($owner, $event))->toBeTrue()
            ->and($policy->publish($owner, $event))->toBeTrue()
            ->and($policy->archive($owner, $event))->toBeTrue()
            ->and($policy->cancel($owner, $event))->toBeTrue()
            ->and($policy->update($otherUser, $event))->toBeFalse()
            ->and($policy->publish($otherUser, $event))->toBeFalse();
    });
});

it('allows public event viewing without granting mutation access', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($viewer): void {
        $event = Event::factory()->published()->create(['visibility' => Event::PUBLIC]);
        $policy = new EventPolicy;

        expect($policy->view($viewer, $event))->toBeTrue()
            ->and($policy->update($viewer, $event))->toBeFalse();
    });
});
