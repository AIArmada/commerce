<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Events\SubscriptionMatched;
use AIArmada\Events\Events\EventPublished;
use AIArmada\Events\Models\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->manager = app(SubscriptionManager::class);
    $this->subscriber = new class {
        public function getMorphClass(): string { return 'user'; }
        public function getKey(): string { return 'user-1'; }
    };
});

it('matches subscriptions for published events', function () {
    EventFacade::fake([SubscriptionMatched::class]);

    $owner = User::query()->create([
        'name' => 'Subscription Owner',
        'email' => 'subscription-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $event = OwnerContext::withOwner($owner, function (): Event {
        return Event::factory()->published()->create([
            'visibility' => 'public',
            'delivery_mode' => 'online',
        ]);
    });

    $this->manager->subscribe($this->subscriber, $event, 'updates', [
        'visibility' => 'public',
        'delivery_mode' => 'online',
    ]);

    event(new EventPublished($event));

    EventFacade::assertDispatched(SubscriptionMatched::class, function (SubscriptionMatched $matched): bool {
        return $matched->subscription->subscriber_id === 'user-1'
            && $matched->subject instanceof Event
            && $matched->trigger === 'event_published';
    });
});

it('does not match subscriptions with non-matching subject', function () {
    $this->manager->subscribe($this->subscriber, $this->subscriber, 'updates');

    $subject = new class {
        public function getMorphClass(): string { return 'event_occurrence'; }
        public function getKey(): string { return 'occ-1'; }
    };

    $matches = iterator_to_array(
        $this->manager->matchingSubscriptions($subject, 'event_occurrence_published', [])
    );

    expect($matches)->toBeEmpty();
});

it('matches subscriptions through the console command using the resolved owner context', function (): void {
    EventFacade::fake([SubscriptionMatched::class]);

    $owner = User::query()->create([
        'name' => 'Subscription Command Owner',
        'email' => 'subscription-command-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $event = OwnerContext::withOwner($owner, function (): Event {
        return Event::factory()->published()->create([
            'visibility' => 'public',
            'delivery_mode' => 'online',
        ]);
    });

    $this->manager->subscribe($this->subscriber, $event, 'updates', [
        'visibility' => 'public',
        'delivery_mode' => 'online',
    ]);

    expect(Artisan::call('engagement:match-subscriptions', [
        'subjectType' => Event::class,
        'subjectId' => $event->id,
        '--trigger' => 'event_published',
    ]))->toBe(0);

    EventFacade::assertDispatched(SubscriptionMatched::class, function (SubscriptionMatched $matched): bool {
        return $matched->subscription->subscriber_id === 'user-1'
            && $matched->subject instanceof Event
            && $matched->trigger === 'event_published';
    });
});
