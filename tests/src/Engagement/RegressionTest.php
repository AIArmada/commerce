<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Enums\BookmarkCollectionStatus;
use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Events\SubscriptionMatched;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\Engagement\Models\BookmarkCollectionItem;
use AIArmada\Engagement\Models\EngagementCounter;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Subscription;
use AIArmada\Engagement\Notifications\EngagementReminderNotification;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementContextSubject;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->actor = new EngagementActor;
    $this->subject = new EngagementSubject;
});

it('stores the same share token that is embedded in the share url', function (): void {
    $share = app(EngagementManager::class)->share($this->actor, $this->subject, ['channel' => 'whatsapp']);

    expect($share->share_token)->toBeString()
        ->and(mb_strlen((string) $share->share_token))->toBe(32);

    parse_str((string) parse_url((string) $share->share_url, PHP_URL_QUERY), $query);

    expect($query['share'] ?? null)->toBe($share->share_token);
});

it('respects an explicit share token in storage and url', function (): void {
    $share = app(EngagementManager::class)->share($this->actor, $this->subject, ['token' => 'explicit-token-1']);

    expect($share->share_token)->toBe('explicit-token-1');

    parse_str((string) parse_url((string) $share->share_url, PHP_URL_QUERY), $query);

    expect($query['share'] ?? null)->toBe('explicit-token-1');
});

it('reconciles counters per owner without an ambient owner', function (): void {
    $ownerA = User::query()->create(['name' => 'Reconcile A', 'email' => 'reconcile-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Reconcile B', 'email' => 'reconcile-b-' . uniqid() . '@example.com', 'password' => 'secret']);
    $subject = EngagementSubject::query()->create(['name' => 'Reconcile Subject', 'email' => 'reconcile-subject-' . uniqid() . '@example.com', 'password' => 'secret']);
    $actorA = EngagementActor::query()->create(['name' => 'Reconcile Actor A', 'email' => 'reconcile-actor-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $actorB = EngagementActor::query()->create(['name' => 'Reconcile Actor B', 'email' => 'reconcile-actor-b-' . uniqid() . '@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($ownerA, fn () => app(EngagementManager::class)->follow($actorA, $subject));
    OwnerContext::withOwner($ownerB, fn () => app(EngagementManager::class)->follow($actorB, $subject));

    OwnerContext::withOwner($ownerA, function (): void {
        EngagementCounter::query()->where('counter_type', 'followers')->update(['count_value' => 99]);
    });

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    expect(Artisan::call('engagement:reconcile-counters'))->toBe(0);

    $countA = OwnerContext::withOwner($ownerA, fn () => app(EngagementCounterService::class)->value($subject, 'followers'));
    $countB = OwnerContext::withOwner($ownerB, fn () => app(EngagementCounterService::class)->value($subject, 'followers'));

    expect($countA)->toBe(1)->and($countB)->toBe(1);
});

it('reconciles a single subject without an ambient owner', function (): void {
    $owner = User::query()->create(['name' => 'Reconcile Single', 'email' => 'reconcile-single-' . uniqid() . '@example.com', 'password' => 'secret']);
    $subject = EngagementSubject::query()->create(['name' => 'Single Subject', 'email' => 'single-subject-' . uniqid() . '@example.com', 'password' => 'secret']);
    $actor = EngagementActor::query()->create(['name' => 'Single Actor', 'email' => 'single-actor-' . uniqid() . '@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($owner, fn () => app(EngagementManager::class)->follow($actor, $subject));
    OwnerContext::withOwner($owner, function (): void {
        EngagementCounter::query()->where('counter_type', 'followers')->update(['count_value' => 42]);
    });

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    expect(Artisan::call('engagement:reconcile-counters', [
        'subjectType' => EngagementSubject::class,
        'subjectId' => $subject->getKey(),
    ]))->toBe(0);

    $count = OwnerContext::withOwner($owner, fn () => app(EngagementCounterService::class)->value($subject, 'followers'));

    expect($count)->toBe(1);
});

it('resolves offset reminders to an absolute remind_at', function (): void {
    $now = CarbonImmutable::parse('2026-09-13 10:00:00');
    CarbonImmutable::setTestNow($now);

    try {
        $reminder = app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'event', [
            'offset_minutes' => 30,
            'anchor_type' => 'start',
        ]);

        // Fixture anchor is now + 1 hour; remind_at = anchor - 30 minutes.
        expect($reminder->remind_at)->not->toBeNull()
            ->and($reminder->remind_at->equalTo($now->addMinutes(30)))->toBeTrue()
            ->and($reminder->offset_minutes)->toBe(30)
            ->and($reminder->anchor_type)->toBe('start');

        $due = collect(app(ReminderManager::class)->dueReminders($now->addMinutes(31)));

        expect($due)->not->toBeEmpty();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('rejects offset reminders without an anchor', function (): void {
    expect(fn () => app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'event', [
        'offset_minutes' => 30,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects offsets that cannot be anchored', function (): void {
    $subject = new class extends Model implements Remindable
    {
        public $incrementing = false;

        protected $keyType = 'string';

        public function getTable(): string
        {
            return 'users';
        }

        public function remindableName(): string
        {
            return 'Anchorless';
        }

        public function reminderAnchorTime(string $anchorType, ?string $anchorCode = null): ?DateTimeInterface
        {
            return null;
        }

        /** @return array<string> */
        public function allowedReminderTypes(): array
        {
            return ['event'];
        }
    };
    $subject->setAttribute($subject->getKeyName(), (string) Str::uuid());

    expect(fn () => app(ReminderManager::class)->setReminder($this->actor, $subject, 'event', [
        'offset_minutes' => 15,
        'anchor_type' => 'start',
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects providing both remind_at and offset_minutes', function (): void {
    expect(fn () => app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'event', [
        'remind_at' => CarbonImmutable::now()->addHour(),
        'offset_minutes' => 15,
        'anchor_type' => 'start',
    ]))->toThrow(InvalidArgumentException::class);
});

it('dedupes subscriptions by normalized criteria hash', function (): void {
    $manager = app(SubscriptionManager::class);

    $first = $manager->subscribe($this->actor, $this->subject, 'updates', ['b' => 2, 'a' => 1]);
    $second = $manager->subscribe($this->actor, $this->subject, 'updates', ['a' => 1, 'b' => 2]);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Subscription::query()->count())->toBe(1);

    $hash = $first->fresh()->criteria_hash;

    expect($hash)->toBeString()->and(mb_strlen((string) $hash))->toBe(64);

    $manager->subscribe($this->actor, $this->subject, 'updates', ['a' => 1]);

    expect(Subscription::query()->count())->toBe(2);
});

it('keeps collection items unique and reactivates removed rows', function (): void {
    $manager = app(EngagementManager::class);

    $bookmark = Bookmark::query()->create([
        'bookmarker_type' => $this->actor->getMorphClass(),
        'bookmarker_id' => $this->actor->getKey(),
        'bookmarkable_type' => $this->subject->getMorphClass(),
        'bookmarkable_id' => $this->subject->getKey(),
        'status' => BookmarkStatus::Active,
    ]);
    $collection = BookmarkCollection::query()->create([
        'name' => 'Repair Collection',
        'visibility' => BookmarkCollection::VISIBILITY_PRIVATE,
        'status' => BookmarkCollectionStatus::Active,
    ]);

    $manager->addBookmarkToCollection($this->actor, $bookmark, $collection);
    $manager->addBookmarkToCollection($this->actor, $bookmark, $collection);

    expect(BookmarkCollectionItem::query()->count())->toBe(1);

    $manager->removeBookmarkFromCollection($this->actor, $bookmark, $collection);

    expect(BookmarkCollectionItem::query()->first()->removed_at)->not->toBeNull();

    $manager->addBookmarkToCollection($this->actor, $bookmark, $collection);

    $item = BookmarkCollectionItem::query()->first();

    expect(BookmarkCollectionItem::query()->count())->toBe(1)
        ->and($item->removed_at)->toBeNull();
});

it('only accepts allowlisted reminder notification classes', function (): void {
    $unlisted = get_class(new class extends Notification
    {
        /** @return array<int, string> */
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    });

    expect(fn () => app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'follow_up', [
        'remind_at' => CarbonImmutable::now()->addHour(),
        'notification_class' => $unlisted,
    ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'follow_up', [
            'remind_at' => CarbonImmutable::now()->addHour(),
            'notification_class' => stdClass::class,
        ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'follow_up', [
            'remind_at' => CarbonImmutable::now()->addHour(),
            'notification_class' => 'App\\Notifications\\DoesNotExist',
        ]))->toThrow(InvalidArgumentException::class);

    $reminder = app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'follow_up', [
        'remind_at' => CarbonImmutable::now()->addHour(),
        'notification_class' => EngagementReminderNotification::class,
    ]);

    expect($reminder->notification_class)->toBe(EngagementReminderNotification::class);
});

it('drops notification_class from reminder mass assignment', function (): void {
    $reminder = Reminder::query()->create([
        'recipient_type' => $this->actor->getMorphClass(),
        'recipient_id' => $this->actor->getKey(),
        'remindable_type' => $this->subject->getMorphClass(),
        'remindable_id' => $this->subject->getKey(),
        'reminder_type' => 'follow_up',
        'status' => ReminderStatus::Pending,
        'remind_at' => CarbonImmutable::now()->addHour(),
        'notification_class' => EngagementReminderNotification::class,
    ]);

    expect($reminder->fresh()->notification_class)->toBeNull();
});

it('refuses to dispatch a reminder with a non-allowlisted stored class', function (): void {
    $unlisted = get_class(new class extends Notification
    {
        /** @return array<int, string> */
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    });

    $reminder = app(ReminderManager::class)->setReminder($this->actor, $this->subject, 'follow_up', [
        'remind_at' => CarbonImmutable::now()->subMinute(),
    ]);
    $reminder->forceFill(['notification_class' => $unlisted])->save();

    expect(fn () => event(new ReminderDue($reminder->fresh())))
        ->toThrow(InvalidArgumentException::class);
});

it('records a failed reminder and continues past dispatch failures', function (): void {
    $recipient = EngagementActor::query()->create([
        'name' => 'Failure Isolation Recipient',
        'email' => 'failure-isolation-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $subject = EngagementSubject::query()->create([
        'name' => 'Failure Isolation Subject',
        'email' => 'failure-isolation-subject-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $bad = app(ReminderManager::class)->setReminder($recipient, $subject, 'follow_up', [
        'remind_at' => CarbonImmutable::now()->subMinutes(5),
    ]);
    $bad->forceFill(['notification_class' => stdClass::class])->save();

    $good = app(ReminderManager::class)->setReminder($recipient, $subject, 'follow_up', [
        'remind_at' => CarbonImmutable::now()->subMinutes(4),
    ]);

    expect(Artisan::call('engagement:send-due-reminders'))->toBe(0);

    expect($bad->fresh()->status)->toBe(ReminderStatus::Failed)
        ->and($bad->fresh()->failure_reason)->not->toBeNull()
        ->and($good->fresh()->status)->toBe(ReminderStatus::Sent);
});

it('emits response created/changed events with reactivation semantics', function (): void {
    Event::fake([
        ResponseCreated::class,
        ResponseChanged::class,
    ]);

    $manager = app(EngagementManager::class);

    $manager->respond($this->actor, $this->subject, 'interested');

    Event::assertDispatchedTimes(ResponseCreated::class, 1);
    Event::assertNotDispatched(ResponseChanged::class);

    $manager->respond($this->actor, $this->subject, 'interested');

    Event::assertDispatchedTimes(ResponseCreated::class, 1);
    Event::assertNotDispatched(ResponseChanged::class);

    $manager->respond($this->actor, $this->subject, 'going');

    Event::assertDispatchedTimes(ResponseChanged::class, 1);

    $manager->cancelResponse($this->actor, $this->subject);
    $restored = $manager->respond($this->actor, $this->subject, 'going');

    Event::assertDispatchedTimes(ResponseCreated::class, 2);
    Event::assertDispatchedTimes(ResponseChanged::class, 1);

    expect($restored->status->value)->toBe('active')
        ->and($restored->cancelled_at)->toBeNull()
        ->and(Response::query()->count())->toBe(1);
});

it('keeps keyed response counters exact across change and cancel', function (): void {
    $manager = app(EngagementManager::class);
    $counters = app(EngagementCounterService::class);

    $manager->respond($this->actor, $this->subject, 'interested');

    expect($counters->value($this->subject, 'responses'))->toBe(1)
        ->and($counters->value($this->subject, 'responses', 'interested'))->toBe(1);

    $manager->respond($this->actor, $this->subject, 'going');

    expect($counters->value($this->subject, 'responses'))->toBe(1)
        ->and($counters->value($this->subject, 'responses', 'interested'))->toBe(0)
        ->and($counters->value($this->subject, 'responses', 'going'))->toBe(1);

    $manager->respond($this->actor, $this->subject, 'going');

    expect($counters->value($this->subject, 'responses'))->toBe(1)
        ->and($counters->value($this->subject, 'responses', 'going'))->toBe(1);

    $manager->cancelResponse($this->actor, $this->subject);

    expect($counters->value($this->subject, 'responses'))->toBe(0)
        ->and($counters->value($this->subject, 'responses', 'going'))->toBe(0);
});

it('authorizes share creation through the policy resolver', function (): void {
    app()->instance(EngagementPolicyResolver::class, new class implements EngagementPolicyResolver
    {
        public function canFollow(mixed $actor, mixed $subject): bool
        {
            return true;
        }

        public function canBookmark(mixed $actor, mixed $subject): bool
        {
            return true;
        }

        public function canRespond(mixed $actor, mixed $subject, string $responseType): bool
        {
            return true;
        }

        public function canReact(mixed $actor, mixed $subject, string $reactionType): bool
        {
            return true;
        }

        public function canSubscribe(mixed $actor, mixed $subject = null, string $subscriptionType = 'updates'): bool
        {
            return true;
        }

        public function canSetReminder(mixed $actor, mixed $subject, string $reminderType): bool
        {
            return true;
        }

        public function canShare(mixed $actor, mixed $subject): bool
        {
            return false;
        }
    });

    expect(fn () => app(EngagementManager::class)->share($this->actor, $this->subject))
        ->toThrow(AuthorizationException::class);
});

it('returns bounded collections from the state resolver', function (): void {
    config()->set('engagement.state.result_limit', 2);

    $subscriptions = app(SubscriptionManager::class);

    foreach (['State One', 'State Two', 'State Three'] as $name) {
        $subscriptions->subscribe($this->actor, new EngagementSubject(['name' => $name]), 'updates');
    }

    $resolved = app(EngagementStateResolver::class)->subscriptionsFor($this->actor);

    expect($resolved)->toBeInstanceOf(Collection::class)->and($resolved->count())->toBe(2);

    $reminders = app(ReminderManager::class);

    foreach (['state_one', 'state_two', 'state_three'] as $type) {
        $reminders->setReminder($this->actor, $this->subject, $type, [
            'remind_at' => CarbonImmutable::now()->addHour(),
        ]);
    }

    $resolvedReminders = app(EngagementStateResolver::class)->remindersFor($this->actor, $this->subject);

    expect($resolvedReminders)->toBeInstanceOf(Collection::class)->and($resolvedReminders->count())->toBe(2);
});

it('rejects overlong free-form inputs at manager boundaries', function (): void {
    $long = str_repeat('x', 300);
    $manager = app(EngagementManager::class);

    expect(fn () => $manager->respond($this->actor, $this->subject, $long))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->react($this->actor, $this->subject, $long))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->follow($this->actor, $this->subject, ['notification_level' => $long]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->share($this->actor, $this->subject, ['channel' => $long]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SubscriptionManager::class)->subscribe($this->actor, $this->subject, $long))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ReminderManager::class)->setReminder($this->actor, $this->subject, $long, ['remind_at' => CarbonImmutable::now()->addHour()]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->bookmark($this->actor, $this->subject, ['notes' => str_repeat('n', 70000)]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->share($this->actor, $this->subject, ['message' => str_repeat('m', 70000)]))
        ->toThrow(InvalidArgumentException::class);
});

it('url-encodes the share token in generated urls', function (): void {
    $share = app(EngagementManager::class)->share($this->actor, $this->subject, ['token' => 'a+b/c=d?&']);

    expect($share->share_token)->toBe('a+b/c=d?&')
        ->and($share->share_url)->toContain('share=a%2Bb%2Fc%3Dd%3F%26');

    parse_str((string) parse_url((string) $share->share_url, PHP_URL_QUERY), $query);

    expect($query['share'] ?? null)->toBe('a+b/c=d?&');
});

it('matches subscriptions without exposing sensitive subject attributes', function (): void {
    $subject = EngagementSubject::query()->create([
        'name' => 'Sensitive Subject',
        'email' => 'sensitive-subject-' . uniqid() . '@example.com',
        'password' => 'secret-protected',
    ]);

    app(SubscriptionManager::class)->subscribe($this->actor, $subject, 'updates', ['password' => 'secret-protected']);
    app(SubscriptionManager::class)->subscribe($this->actor, $subject, 'updates', []);

    Event::fake([SubscriptionMatched::class]);

    expect(Artisan::call('engagement:match-subscriptions', [
        'subjectType' => EngagementSubject::class,
        'subjectId' => $subject->getKey(),
        '--trigger' => 'profile_updated',
    ]))->toBe(0);

    Event::assertDispatchedTimes(SubscriptionMatched::class, 1);
});

it('exposes subject-approved match context via subscriptionMatchContext', function (): void {
    $subject = EngagementContextSubject::query()->create([
        'name' => 'Context Subject',
        'email' => 'context-subject-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    app(SubscriptionManager::class)->subscribe($this->actor, $subject, 'updates', ['tier' => 'gold']);

    Event::fake([SubscriptionMatched::class]);

    expect(Artisan::call('engagement:match-subscriptions', [
        'subjectType' => EngagementContextSubject::class,
        'subjectId' => $subject->getKey(),
        '--trigger' => 'tier_updated',
    ]))->toBe(0);

    Event::assertDispatched(SubscriptionMatched::class);
});

it('dedupes identical pending reminders', function (): void {
    $at = CarbonImmutable::now()->addHour();
    $manager = app(ReminderManager::class);

    $first = $manager->setReminder($this->actor, $this->subject, 'follow_up', ['remind_at' => $at]);
    $second = $manager->setReminder($this->actor, $this->subject, 'follow_up', ['remind_at' => $at]);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Reminder::query()->count())->toBe(1);

    $manager->setReminder($this->actor, $this->subject, 'follow_up', ['remind_at' => CarbonImmutable::now()->addHours(2)]);

    expect(Reminder::query()->count())->toBe(2);
});

it('ships hot-path composite indexes and identity uniques', function (): void {
    $tables = config('engagement.database.tables');

    $hasIndex = function (string $table, array $columns, bool $unique = false): bool {
        foreach (Schema::getIndexes($table) as $index) {
            $indexColumns = array_map('strtolower', (array) ($index['columns'] ?? []));

            if ($indexColumns === $columns && (bool) ($index['unique'] ?? false) === $unique) {
                return true;
            }
        }

        return false;
    };

    expect(Schema::hasColumn($tables['subscriptions'], 'criteria_hash'))->toBeTrue()
        ->and($hasIndex($tables['reminders'], ['status', 'remind_at']))->toBeTrue()
        ->and($hasIndex($tables['subscriptions'], ['status', 'subscribable_type', 'subscribable_id']))->toBeTrue()
        ->and($hasIndex($tables['reactions'], ['reactable_type', 'reactable_id', 'reaction_type', 'status']))->toBeTrue()
        ->and($hasIndex($tables['responses'], ['respondable_type', 'respondable_id', 'response_type', 'status']))->toBeTrue()
        ->and($hasIndex($tables['subscriptions'], ['subscriber_type', 'subscriber_id', 'subscribable_type', 'subscribable_id', 'subscription_type', 'criteria_hash', 'owner_type', 'owner_id'], true))->toBeTrue()
        ->and($hasIndex($tables['bookmark_collection_items'], ['bookmark_collection_id', 'bookmark_id', 'owner_type', 'owner_id'], true))->toBeTrue();
});
