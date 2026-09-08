<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Events\SubscriptionMatched;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use AIArmada\Events\Events\EventPublished;
use AIArmada\Events\Models\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function (): void {
    $this->manager = app(SubscriptionManager::class);
    $this->subscriber = new EngagementActor;
});

it('does not auto-match subscriptions when an events publication is dispatched', function (): void {
    $owner = User::query()->create([
        'name' => 'Publication Separation Owner',
        'email' => 'publication-separation-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $event = OwnerContext::withOwner($owner, function (): Event {
        return Event::factory()->published()->create();
    });

    OwnerContext::withOwner($owner, function (): void {
        $this->manager->subscribe($this->subscriber, null, 'updates');
    });

    EventFacade::fake([SubscriptionMatched::class]);

    event(new EventPublished($event));

    EventFacade::assertNotDispatched(SubscriptionMatched::class);
});

it('does not match subscriptions with non-matching subject', function (): void {
    $this->manager->subscribe($this->subscriber, new EngagementSubject, 'updates');

    $subject = new EngagementSubject;

    $matches = iterator_to_array(
        $this->manager->matchingSubscriptions($subject, 'event_occurrence_published', [])
    );

    expect($matches)->toBeEmpty();
});

it('matches subscriptions through the console command', function (): void {
    $owner = User::query()->create([
        'name' => 'Subscription Command Owner',
        'email' => 'subscription-command-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $subject = OwnerContext::withOwner($owner, function (): EngagementSubject {
        return EngagementSubject::query()->create([
            'name' => 'Subscription Command Subject',
            'email' => 'subscription-command-subject-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($subject): void {
        $this->manager->subscribe($this->subscriber, $subject, 'updates');
    });

    EventFacade::fake([SubscriptionMatched::class]);

    expect(Artisan::call('engagement:match-subscriptions', [
        'subjectType' => EngagementSubject::class,
        'subjectId' => $subject->id,
        '--trigger' => 'event_published',
    ]))->toBe(0);

    EventFacade::assertDispatched(SubscriptionMatched::class, function (SubscriptionMatched $matched): bool {
        return $matched->subscription->subscriber_id === $this->subscriber->getKey()
            && $matched->subject instanceof EngagementSubject
            && $matched->trigger === 'event_published';
    });
});

it('matches global subscriptions for subjects without an owner relation', function (): void {
    $subject = OwnerContext::withOwner(null, function (): EngagementSubject {
        return EngagementSubject::query()->create([
            'name' => 'Global Subscription Subject',
            'email' => 'global-subject-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
    });

    OwnerContext::withOwner(null, function () use ($subject): void {
        $this->manager->subscribe($this->subscriber, $subject, 'updates');
    });

    EventFacade::fake([SubscriptionMatched::class]);

    expect(Artisan::call('engagement:match-subscriptions', [
        'subjectType' => EngagementSubject::class,
        'subjectId' => $subject->id,
        '--trigger' => 'user_updated',
    ]))->toBe(0);

    EventFacade::assertDispatched(SubscriptionMatched::class);
});
