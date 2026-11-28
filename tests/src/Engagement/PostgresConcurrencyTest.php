<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\EntryPoints\Jobs\DispatchManagedNotificationJob;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationReference;
use AIArmada\Communications\Features\Delivery\Actions\CommunicationManagerService;
use AIArmada\Communications\Features\Delivery\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Models\Communication;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Features\Counters\Actions\UpdateEngagementCountersAction;
use AIArmada\Engagement\Features\Counters\Models\EngagementCounter;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Features\Reminders\EntryPoints\Listeners\DispatchReminderThroughCommunications;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class EngagementPostgresNotifiable extends User
{
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}

final class EngagementPostgresEmptyNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [];
    }
}

final class EngagementPostgresMailNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Reminder')->line('Reminder');
    }
}

/**
 * @return array{0: Connection, 1: Connection, 2: string, 3: string}
 */
function engagementPostgresSetup(): array
{
    $original = (string) DB::getDefaultConnection();
    $schema = 'engagement_test_' . bin2hex(random_bytes(6));
    $config = [
        'driver' => 'pgsql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_DATABASE') ?: 'commerce_test',
        'username' => getenv('DB_USERNAME') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => $schema,
        'sslmode' => 'prefer',
    ];

    config([
        'database.connections.engagement_pg_first' => $config,
        'database.connections.engagement_pg_second' => $config,
        'engagement.database.json_column_type' => 'jsonb',
        'engagement.owner.enabled' => true,
        'engagement.owner.include_global' => false,
        'engagement.owner.auto_assign_on_create' => true,
        'communications.database.json_column_type' => 'jsonb',
        'communications.features.owner.enabled' => true,
        'communications.features.owner.include_global' => false,
        'communications.features.owner.auto_assign_on_create' => true,
    ]);

    DB::purge('engagement_pg_first');
    DB::purge('engagement_pg_second');
    $first = DB::connection('engagement_pg_first');
    $first->statement(sprintf('CREATE SCHEMA "%s"', $schema));

    $exitCode = Artisan::call('migrate', [
        '--database' => 'engagement_pg_first',
        '--path' => dirname(__DIR__, 3) . '/packages/engagement/database/migrations',
        '--realpath' => true,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    $communicationsExitCode = Artisan::call('migrate', [
        '--database' => 'engagement_pg_first',
        '--path' => dirname(__DIR__, 3) . '/packages/communications/database/migrations',
        '--realpath' => true,
        '--force' => true,
    ]);

    expect($communicationsExitCode)->toBe(0);

    $first->getSchemaBuilder()->create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    DB::setDefaultConnection('engagement_pg_first');

    return [$first, DB::connection('engagement_pg_second'), $schema, $original];
}

/**
 * @param  array{0: Connection, 1: Connection, 2: string, 3: string}  $context
 */
function engagementPostgresTeardown(array $context): void
{
    [$first, , $schema, $original] = $context;

    DB::setDefaultConnection($original);
    DB::purge('engagement_pg_second');
    $first->statement(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schema));
    DB::purge('engagement_pg_first');
}

beforeEach(function (): void {
    if (getenv('ENGAGEMENT_POSTGRES_CONCURRENCY') !== 'true' && getenv('DB_CONNECTION') !== 'pgsql') {
        $this->markTestSkipped('Set ENGAGEMENT_POSTGRES_CONCURRENCY=true or DB_CONNECTION=pgsql with PostgreSQL DB_* settings.');
    }
});

it('fences reminder claims and stale finalization on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();

    try {
        $owner = User::query()->create([
            'name' => 'Engagement PostgreSQL Owner',
            'email' => 'engagement-pg-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function (): void {
            $now = CarbonImmutable::now();
            $reminder = Reminder::factory()->create([
                'remindable_type' => EngagementSubject::class,
                'remindable_id' => (string) Str::uuid(),
                'recipient_type' => User::class,
                'recipient_id' => (string) Str::uuid(),
                'status' => ReminderStatus::Pending,
                'remind_at' => $now->subMinute(),
            ]);
            $firstToken = (string) Str::uuid();
            $secondToken = (string) Str::uuid();
            $manager = app(ManageRemindersAction::class);

            DB::setDefaultConnection('engagement_pg_first');

            expect($manager->claimForDelivery($reminder, $now, $firstToken, $now->addMinutes(5), 3))->toBeTrue();

            DB::setDefaultConnection('engagement_pg_second');

            expect($manager->claimForDelivery($reminder, $now, $secondToken, $now->addMinutes(5), 3))->toBeFalse();

            DB::setDefaultConnection('engagement_pg_first');
            $reminder->refresh()->forceFill(['claim_expires_at' => $now->subSecond()])->save();

            DB::setDefaultConnection('engagement_pg_second');

            expect($manager->claimForDelivery($reminder, $now, $secondToken, $now->addMinutes(5), 3))->toBeTrue();

            DB::setDefaultConnection('engagement_pg_first');

            expect($manager->completeClaim($reminder, $firstToken))->toBeFalse()
                ->and($manager->failClaim($reminder, $firstToken, 'stale worker'))->toBeFalse()
                ->and($reminder->fresh()->status)->toBe(ReminderStatus::Pending)
                ->and($reminder->fresh()->claim_token)->toBe($secondToken);

            DB::setDefaultConnection('engagement_pg_second');

            expect($manager->completeClaim($reminder, $secondToken))->toBeTrue();

            DB::setDefaultConnection('engagement_pg_first');

            expect($reminder->fresh()->status)->toBe(ReminderStatus::Sent)
                ->and($reminder->fresh()->attempts)->toBe(2)
                ->and($reminder->fresh()->claim_token)->toBeNull();
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('holds the counter lock across a recount on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();

    try {
        $owner = User::query()->create([
            'name' => 'Counter PostgreSQL Owner',
            'email' => 'counter-pg-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function () use ($owner): void {
            $subject = EngagementSubject::query()->create([
                'name' => 'Counter Subject',
                'email' => 'counter-subject-' . Str::lower(Str::random(8)) . '@example.com',
                'password' => 'secret',
            ]);

            Follow::factory()->create([
                'follower_type' => EngagementSubject::class,
                'follower_id' => (string) Str::uuid(),
                'followable_type' => $subject->getMorphClass(),
                'followable_id' => (string) $subject->getKey(),
            ]);

            $action = app(UpdateEngagementCountersAction::class);
            $first = DB::connection('engagement_pg_first');
            $second = DB::connection('engagement_pg_second');
            $event = new FollowCreated(
                (string) Str::uuid(),
                EngagementSubject::class,
                (string) Str::uuid(),
                $subject->getMorphClass(),
                (string) $subject->getKey(),
                $owner->getMorphClass(),
                (string) $owner->getKey(),
            );

            $first->beginTransaction();

            try {
                DB::setDefaultConnection('engagement_pg_first');
                $action->recalculate($subject);

                DB::setDefaultConnection('engagement_pg_second');
                $second->statement("SET lock_timeout = '1s'");

                expect(fn () => $action->onFollowCreated($event))
                    ->toThrow(QueryException::class, '55P03');
            } finally {
                DB::setDefaultConnection('engagement_pg_first');

                if ($first->transactionLevel() > 0) {
                    $first->commit();
                }
            }

            $second->statement('SET lock_timeout = 0');
            DB::setDefaultConnection('engagement_pg_second');
            $action->onFollowCreated($event);

            DB::setDefaultConnection('engagement_pg_first');

            expect($action->value($subject, 'followers'))->toBe(2)
                ->and($subject->getConnection()->table((new Follow)->getTable())->count())->toBe(1);
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('serializes missing counter row creation on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();

    try {
        $owner = User::query()->create([
            'name' => 'Missing Counter Owner',
            'email' => 'missing-counter-pg-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function () use ($owner): void {
            $subject = EngagementSubject::query()->create([
                'name' => 'Missing Counter Subject',
                'email' => 'missing-counter-subject-' . Str::lower(Str::random(8)) . '@example.com',
                'password' => 'secret',
            ]);
            $action = app(UpdateEngagementCountersAction::class);
            $first = DB::connection('engagement_pg_first');
            $second = DB::connection('engagement_pg_second');
            $event = new FollowCreated(
                (string) Str::uuid(),
                EngagementSubject::class,
                (string) Str::uuid(),
                $subject->getMorphClass(),
                (string) $subject->getKey(),
                $owner->getMorphClass(),
                (string) $owner->getKey(),
            );

            $first->beginTransaction();

            try {
                DB::setDefaultConnection('engagement_pg_first');
                $action->onFollowCreated($event);

                DB::setDefaultConnection('engagement_pg_second');
                $second->statement("SET lock_timeout = '1s'");

                expect(fn () => $action->onFollowCreated($event))
                    ->toThrow(QueryException::class, '55P03');
            } finally {
                DB::setDefaultConnection('engagement_pg_first');

                if ($first->transactionLevel() > 0) {
                    $first->commit();
                }
            }

            $second->statement('SET lock_timeout = 0');
            DB::setDefaultConnection('engagement_pg_second');
            $action->onFollowCreated($event);

            DB::setDefaultConnection('engagement_pg_first');

            expect($action->value($subject, 'followers'))->toBe(2)
                ->and($subject->getConnection()->table((new Follow)->getTable())->count())->toBe(0)
                ->and(EngagementCounter::query()->forOwner()
                    ->where('counter_type', 'followers')
                    ->where('counter_key', '')
                    ->count())->toBe(1);
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('serializes response counter changes under the aggregate lock on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();

    try {
        $owner = User::query()->create([
            'name' => 'Response Counter Owner',
            'email' => 'response-counter-pg-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function () use ($owner): void {
            $subject = EngagementSubject::query()->create([
                'name' => 'Response Counter Subject',
                'email' => 'response-counter-subject-' . Str::lower(Str::random(8)) . '@example.com',
                'password' => 'secret',
            ]);

            foreach (['interested', 'going'] as $responseType) {
                Response::factory()->create([
                    'responder_type' => EngagementSubject::class,
                    'responder_id' => (string) Str::uuid(),
                    'respondable_type' => $subject->getMorphClass(),
                    'respondable_id' => (string) $subject->getKey(),
                    'response_type' => $responseType,
                ]);
            }

            $action = app(UpdateEngagementCountersAction::class);
            $first = DB::connection('engagement_pg_first');
            $second = DB::connection('engagement_pg_second');
            $event = new ResponseChanged(
                (string) Str::uuid(),
                EngagementSubject::class,
                (string) Str::uuid(),
                $subject->getMorphClass(),
                (string) $subject->getKey(),
                $owner->getMorphClass(),
                (string) $owner->getKey(),
                'going',
                'interested',
            );

            $first->beginTransaction();

            try {
                DB::setDefaultConnection('engagement_pg_first');
                $action->recalculateResponses($subject);

                DB::setDefaultConnection('engagement_pg_second');
                $second->statement("SET lock_timeout = '1s'");

                expect(fn () => $action->onResponseChanged($event))
                    ->toThrow(QueryException::class, '55P03');
            } finally {
                DB::setDefaultConnection('engagement_pg_first');

                if ($first->transactionLevel() > 0) {
                    $first->commit();
                }
            }

            $second->statement('SET lock_timeout = 0');
            DB::setDefaultConnection('engagement_pg_second');
            $action->onResponseChanged($event);

            DB::setDefaultConnection('engagement_pg_first');

            expect($action->value($subject, 'responses'))->toBe(2)
                ->and($action->value($subject, 'responses', 'interested'))->toBe(0)
                ->and($action->value($subject, 'responses', 'going'))->toBe(2);
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('keeps same-subject counter partitions independent across owners on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();

    try {
        $ownerA = User::query()->create([
            'name' => 'Partition Owner A',
            'email' => 'partition-a-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
        $ownerB = User::query()->create([
            'name' => 'Partition Owner B',
            'email' => 'partition-b-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
        $subject = OwnerContext::withOwner($ownerA, fn (): EngagementSubject => EngagementSubject::query()->create([
            'name' => 'Shared Partition Subject',
            'email' => 'partition-subject-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]));
        $action = app(UpdateEngagementCountersAction::class);
        $event = static fn (User $owner): FollowCreated => new FollowCreated(
            (string) Str::uuid(),
            EngagementSubject::class,
            (string) Str::uuid(),
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $owner->getMorphClass(),
            (string) $owner->getKey(),
        );

        $first = DB::connection('engagement_pg_first');
        $first->beginTransaction();

        try {
            DB::setDefaultConnection('engagement_pg_first');
            OwnerContext::withOwner($ownerA, fn () => $action->onFollowCreated($event($ownerA)));

            DB::setDefaultConnection('engagement_pg_second');
            OwnerContext::withOwner($ownerB, fn () => $action->onFollowCreated($event($ownerB)));
        } finally {
            DB::setDefaultConnection('engagement_pg_first');

            if ($first->transactionLevel() > 0) {
                $first->commit();
            }
        }

        $valueA = OwnerContext::withOwner($ownerA, fn (): int => $action->value($subject, 'followers'));
        $valueB = OwnerContext::withOwner($ownerB, fn (): int => $action->value($subject, 'followers'));

        expect($valueA)->toBe(1)
            ->and($valueB)->toBe(1);
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('recovers a concurrent aggregate counter insert without aborting the transaction on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();

    try {
        $owner = User::query()->create([
            'name' => 'Counter Savepoint Owner',
            'email' => 'counter-savepoint-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function () use ($owner): void {
            $subject = EngagementSubject::query()->create([
                'name' => 'Counter Savepoint Subject',
                'email' => 'counter-savepoint-subject-' . Str::lower(Str::random(8)) . '@example.com',
                'password' => 'secret',
            ]);

            Follow::factory()->create([
                'follower_type' => EngagementSubject::class,
                'follower_id' => (string) Str::uuid(),
                'followable_type' => $subject->getMorphClass(),
                'followable_id' => (string) $subject->getKey(),
            ]);

            Response::factory()->create([
                'responder_type' => EngagementSubject::class,
                'responder_id' => (string) Str::uuid(),
                'respondable_type' => $subject->getMorphClass(),
                'respondable_id' => (string) $subject->getKey(),
                'response_type' => 'going',
            ]);

            $action = app(UpdateEngagementCountersAction::class);
            $second = DB::connection('engagement_pg_second');
            $competing = true;

            EngagementCounter::creating(function (EngagementCounter $counter) use (&$competing, $second, $owner): void {
                if (! $competing) {
                    return;
                }

                $competing = false;

                $second->table((new EngagementCounter)->getTable())->insert([
                    'id' => (string) Str::uuid(),
                    'subject_type' => $counter->subject_type,
                    'subject_id' => $counter->subject_id,
                    'counter_type' => $counter->counter_type,
                    'counter_key' => $counter->counter_key,
                    'count_value' => 0,
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => (string) $owner->getKey(),
                    'created_at' => CarbonImmutable::now(),
                    'updated_at' => CarbonImmutable::now(),
                ]);
            });

            try {
                $action->recalculate($subject);
            } finally {
                $competing = false;
            }

            expect($action->value($subject, 'followers'))->toBe(1)
                ->and($action->value($subject, 'bookmarks'))->toBe(0);

            $secondResponse = Response::factory()->create([
                'responder_type' => EngagementSubject::class,
                'responder_id' => (string) Str::uuid(),
                'respondable_type' => $subject->getMorphClass(),
                'respondable_id' => (string) $subject->getKey(),
                'response_type' => 'interested',
            ]);

            $action->onResponseCreated(new ResponseCreated(
                (string) $secondResponse->getKey(),
                EngagementSubject::class,
                (string) Str::uuid(),
                $subject->getMorphClass(),
                (string) $subject->getKey(),
                $owner->getMorphClass(),
                (string) $owner->getKey(),
                'interested',
            ));

            expect($action->value($subject, 'responses'))->toBe(2)
                ->and($action->value($subject, 'responses', 'interested'))->toBe(1)
                ->and($action->value($subject, 'responses', 'going'))->toBe(1);
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('serializes concurrent managed notification and reminder dispatches on PostgreSQL', function (): void {
    $context = engagementPostgresSetup();
    Queue::fake();

    try {
        $owner = User::query()->create([
            'name' => 'Reminder Dispatch Owner',
            'email' => 'reminder-dispatch-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
        $notifiable = EngagementPostgresNotifiable::query()->create([
            'name' => 'Reminder Dispatch Notifiable',
            'email' => 'reminder-notifiable-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function () use ($owner, $notifiable): void {
            $reminder = Reminder::factory()->create([
                'remindable_type' => EngagementSubject::class,
                'remindable_id' => (string) Str::uuid(),
                'recipient_type' => $notifiable->getMorphClass(),
                'recipient_id' => (string) $notifiable->getKey(),
                'status' => ReminderStatus::Pending,
                'remind_at' => CarbonImmutable::now()->subMinute(),
            ]);

            $idempotencyKey = 'engagement-reminder:' . $reminder->getKey();
            $competing = true;

            Communication::creating(function (Communication $communication) use (&$competing, $owner, $notifiable, $idempotencyKey): void {
                if (! $competing) {
                    return;
                }

                $competing = false;
                $default = DB::getDefaultConnection();
                DB::setDefaultConnection('engagement_pg_second');

                try {
                    OwnerContext::withOwner($owner, fn (): Communication => app(DispatchManagedNotificationAction::class)->handle(
                        $notifiable,
                        new EngagementPostgresMailNotification,
                        new CommunicationContextData(
                            purpose: 'engagement.reminder',
                            idempotencyKey: $idempotencyKey,
                            metadata: ['competing' => true],
                        ),
                    ));
                } finally {
                    DB::setDefaultConnection($default);
                }
            });

            try {
                app(DispatchReminderThroughCommunications::class)->handle(new ReminderDue(
                    $reminder->id,
                    $reminder->recipient_type,
                    $reminder->recipient_id,
                    $reminder->remindable_type,
                    $reminder->remindable_id,
                    $reminder->owner_type,
                    $reminder->owner_id,
                    $reminder->reminder_type,
                ));
            } finally {
                $competing = false;
            }

            $communications = Communication::query()->withoutOwnerScope()
                ->where('idempotency_key', $idempotencyKey)
                ->get();

            expect($communications)->toHaveCount(1)
                ->and(CommunicationReference::query()->withoutOwnerScope()
                    ->where('communication_id', $communications->sole()->getKey())
                    ->where('reference_type', Reminder::class)
                    ->where('reference_id', (string) $reminder->getKey())
                    ->where('role', 'engagement_reminder')
                    ->count())->toBe(1);

            Queue::assertPushed(DispatchManagedNotificationJob::class, 1);
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});

it('rejects duplicate manager notifications durably without a shared cache store', function (): void {
    $context = engagementPostgresSetup();

    try {
        $owner = User::query()->create([
            'name' => 'Manager Cache Owner',
            'email' => 'manager-cache-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);
        $notifiable = EngagementPostgresNotifiable::query()->create([
            'name' => 'Manager Cache Notifiable',
            'email' => 'manager-cache-notifiable-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, function () use ($notifiable): void {
            config()->set('communications.cache.idempotency_store', 'array');

            $manager = app(CommunicationManagerService::class);
            $context = new CommunicationContextData(
                purpose: 'pg-manager-idempotency',
                idempotencyKey: 'pg-manager-idempotency-key',
            );

            $manager->notify($notifiable, new EngagementPostgresEmptyNotification, $context);

            Cache::store('array')->flush();

            expect(fn (): string => $manager->notify($notifiable, new EngagementPostgresEmptyNotification, $context))
                ->toThrow(RuntimeException::class, 'Duplicate communication detected for idempotency key: pg-manager-idempotency-key')
                ->and(Communication::query()->withoutOwnerScope()->where('idempotency_key', 'pg-manager-idempotency-key')->count())->toBe(1);
        });
    } finally {
        engagementPostgresTeardown($context);
    }
});
