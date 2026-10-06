<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Exceptions\CommerceSignalRecordingFailed;
use AIArmada\Signals\Exceptions\CommerceSignalTransactionControlFailed;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\CommerceSignalsRecorder;
use AIArmada\Signals\Services\SignalAlertEvaluator;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('affiliate_conversions');
    Schema::dropIfExists('affiliate_attributions');
    Schema::dropIfExists('affiliates');

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('code')->unique();
        $table->string('handle', 40)->unique();
        $table->string('name');
        $table->string('status')->default(Active::class);
        $table->string('commission_type')->default(CommissionType::Percentage->value);
        $table->unsignedInteger('commission_rate')->default(1000);
        $table->string('currency', 3)->default('MYR');
        $table->string('default_voucher_code')->nullable();
        $table->json('metadata')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->timestamps();
    });

    Schema::create('affiliate_attributions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('affiliate_id');
        $table->string('affiliate_code');
        $table->string('subject_key')->nullable();
        $table->string('subject_instance')->nullable();
        $table->string('cart_identifier')->nullable()->index();
        $table->string('cart_instance')->default('default');
        $table->string('cookie_value')->nullable()->index();
        $table->string('voucher_code')->nullable();
        $table->string('source')->nullable();
        $table->string('medium')->nullable();
        $table->string('campaign')->nullable();
        $table->string('term')->nullable();
        $table->string('content')->nullable();
        $table->text('landing_url')->nullable();
        $table->text('referrer_url')->nullable();
        $table->foreignUuid('user_id')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->json('metadata')->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_conversions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('affiliate_id');
        $table->foreignUuid('affiliate_attribution_id')->nullable();
        $table->string('affiliate_code');
        $table->string('subject_key')->nullable();
        $table->string('subject_instance')->nullable();
        $table->string('voucher_code')->nullable();
        $table->string('external_reference')->nullable();
        $table->string('conversion_type')->nullable();
        $table->unsignedBigInteger('subtotal_minor')->default(0);
        $table->unsignedBigInteger('value_minor')->default(0);
        $table->unsignedBigInteger('commission_minor')->default(0);
        $table->string('commission_currency', 3)->default('MYR');
        $table->string('status')->default(ApprovedConversion::class);
        $table->string('channel')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->json('metadata')->nullable();
        $table->timestamp('occurred_at')->nullable();
        $table->timestamps();
    });

    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        /** @var list<Throwable> */
        public array $reported = [];

        public function report(Throwable $e)
        {
            $this->reported[] = $e;
        }

        public function shouldReport(Throwable $e)
        {
            return true;
        }

        public function render($request, Throwable $e)
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e)
        {
            throw $e;
        }
    });
});

function malformedConversionData(): AffiliateConversionData
{
    return new AffiliateConversionData(id: '', affiliateId: '', affiliateCode: '');
}

function reportedFailures(): array
{
    return app(ExceptionHandler::class)->reported;
}

it('reports a malformed automatic recording and continues dispatch', function (): void {
    Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));

    $failures = reportedFailures();

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and($failures[0]->context())->toMatchArray([
            'source_event_class' => AffiliateConversionRecorded::class,
            'recorder_method' => 'recordAffiliateConversionRecorded',
            'phase' => 'recording',
        ])
        ->and($failures[0]->getPrevious())->toBeInstanceOf(InvalidArgumentException::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('executes later listeners after a recording failure', function (): void {
    $ran = false;

    Event::listen(AffiliateConversionRecorded::class, function () use (&$ran): void {
        $ran = true;
    });

    Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));

    expect($ran)->toBeTrue()
        ->and(reportedFailures())->toHaveCount(1);
});

it('keeps direct recorder calls strict on the same invalid source', function (): void {
    expect(fn (): ?SignalEvent => app(CommerceSignalsRecorder::class)->recordAffiliateConversionRecorded(malformedConversionData()))
        ->toThrow(InvalidArgumentException::class);
});

it('records a later valid event after a failure', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Isolation Property',
        'slug' => 'isolation-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $affiliate = Affiliate::query()->create([
        'code' => 'ISOLATION-ALI',
        'name' => 'Isolation Ali',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
    $affiliate->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    $conversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'subject_key' => 'event:isolation',
        'subject_instance' => 'share-link',
        'value_minor' => 28000,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
    $conversion->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));
    Event::dispatch(new AffiliateConversionRecorded(AffiliateConversionData::fromModel($conversion)));

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->event_name)->toBe('affiliate.conversion.recorded')
        ->and(reportedFailures())->toHaveCount(1);
});

it('restores owner context after a failure', function (): void {
    $before = OwnerContext::resolve();

    Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));

    $after = OwnerContext::resolve();

    expect($after?->getKey())->toBe($before?->getKey())
        ->and(OwnerContext::hasOverride())->toBeFalse();
});

it('rolls back only the recording attempt inside an outer transaction', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Outer Txn Property',
        'slug' => 'outer-txn-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $affiliate = Affiliate::query()->create([
        'code' => 'TXN-ALI',
        'name' => 'Txn Ali',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
    $affiliate->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    $conversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'value_minor' => 12000,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
    $conversion->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    DB::transaction(function () use ($conversion): void {
        Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));
        Event::dispatch(new AffiliateConversionRecorded(AffiliateConversionData::fromModel($conversion)));
    });

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->event_name)->toBe('affiliate.conversion.recorded')
        ->and(reportedFailures())->toHaveCount(1);
});

it('keeps the persisted event when immediate alert evaluation fails', function (): void {
    enableThrowingSyncAlertEvaluation();

    $property = TrackedProperty::query()->create([
        'name' => 'Alert Property',
        'slug' => 'alert-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    createFailingAlertRule($property);

    $event = ingestConversionForAlerts('ALERT-ALI');

    $failures = reportedFailures();

    expect($event)->toBeInstanceOf(SignalEvent::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1)
        ->and($failures)->toHaveCount(1)
        ->and($failures[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and($failures[0]->context())->toMatchArray([
            'phase' => 'alert_evaluation',
            'signal_event_id' => $event->getKey(),
        ]);
});

it('keeps the persisted event and later commit callbacks when deferred alert evaluation fails', function (): void {
    enableThrowingSyncAlertEvaluation();

    $property = TrackedProperty::query()->create([
        'name' => 'Deferred Alert Property',
        'slug' => 'deferred-alert-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    createFailingAlertRule($property);

    $afterCommitRan = false;

    DB::transaction(function () use (&$afterCommitRan): void {
        ingestConversionForAlerts('DEFERRED-ALI');

        DB::afterCommit(function () use (&$afterCommitRan): void {
            $afterCommitRan = true;
        });
    });

    $failures = reportedFailures();

    expect($afterCommitRan)->toBeTrue()
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1)
        ->and($failures)->toHaveCount(1)
        ->and($failures[0]->context())->toMatchArray(['phase' => 'alert_evaluation']);
});

function enableThrowingSyncAlertEvaluation(): void
{
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', false);

    app()->instance(SignalAlertEvaluator::class, new class
    {
        public function evaluate(SignalAlertRule $rule): array
        {
            throw new RuntimeException('alert evaluator boom');
        }
    });
}

function createFailingAlertRule(TrackedProperty $property): void
{
    SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Failing Rule',
        'slug' => 'failing-rule-' . uniqid(),
        'metric_key' => 'events.total',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 0,
        'severity' => 'warning',
        'is_active' => true,
    ]);
}

it('reports mapping-phase failures without touching the recorder', function (): void {
    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    Event::dispatch(new OrderPaid(new Order, '', 'stripe', 100));

    $failures = reportedFailures();

    expect($laterRan)->toBeTrue()
        ->and($failures)->toHaveCount(1)
        ->and($failures[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and($failures[0]->context())->toMatchArray([
            'source_event_class' => OrderPaid::class,
            'recorder_method' => 'recordOrderPaid',
            'phase' => 'mapping',
            'integration' => 'orders',
        ])
        ->and($failures[0]->getPrevious())->toBeInstanceOf(InvalidArgumentException::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('dispatches normally when the exception reporter itself throws', function (): void {
    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        public function report(Throwable $e)
        {
            throw new RuntimeException('reporter boom');
        }

        public function shouldReport(Throwable $e)
        {
            return true;
        }

        public function render($request, Throwable $e)
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e)
        {
            throw $e;
        }
    });

    $laterRan = false;

    Event::listen(AffiliateConversionRecorded::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));

    expect($laterRan)->toBeTrue()
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('preserves host writes in an outer transaction around a recording failure', function (): void {
    $owner = User::query()->firstOrFail();

    DB::transaction(function (): void {
        Affiliate::query()->create([
            'code' => 'HOST-WRITE-ALI',
            'name' => 'Host Write Ali',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage->value,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);

        Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));
    });

    expect(Affiliate::query()->where('code', 'HOST-WRITE-ALI')->exists())->toBeTrue()
        ->and(reportedFailures())->toHaveCount(1)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('rolls recording writes back with an outer transaction rollback', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Rollback Property',
        'slug' => 'rollback-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $affiliate = Affiliate::query()->create([
        'code' => 'ROLLBACK-ALI',
        'name' => 'Rollback Ali',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
    $affiliate->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    $conversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'value_minor' => 9000,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
    $conversion->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    try {
        DB::transaction(function () use ($conversion): void {
            Event::dispatch(new AffiliateConversionRecorded(AffiliateConversionData::fromModel($conversion)));

            // The recording must exist before the rollback; otherwise this
            // test would also pass if recording silently became a no-op.
            expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);

            throw new RuntimeException('host rollback probe');
        });

        $this->fail('The outer transaction should have rolled back.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('host rollback probe');
    }

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('keeps the active owner override intact across a recording failure', function (): void {
    $ownerB = User::query()->create([
        'name' => 'Override Owner B',
        'email' => 'override-owner-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerB, function (): void {
        Event::dispatch(new AffiliateConversionRecorded(malformedConversionData()));

        expect(OwnerContext::resolve()?->getKey())->toBe(User::query()->where('email', 'override-owner-b@example.com')->firstOrFail()->getKey())
            ->and(OwnerContext::hasOverride())->toBeTrue();
    });

    expect(OwnerContext::hasOverride())->toBeFalse();
});

it('rolls back recording-side writes and restores context when enrichment fails mid-attempt', function (): void {
    $ownerA = User::query()->firstOrFail();
    $ownerB = User::query()->create([
        'name' => 'Enrichment Owner B',
        'email' => 'enrichment-owner-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerB, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Enrichment Property B',
            'slug' => 'enrichment-property-b',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $order = new Order;
    $order->forceFill([
        'grand_total' => 5000,
        'paid_at' => now(),
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $probe = new class
    {
        public ?Model $seenOwner = null;

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            $this->seenOwner = OwnerContext::resolve();

            User::query()->create([
                'name' => 'Recording Marker',
                'email' => 'recording-marker@example.com',
                'password' => 'secret',
            ]);

            throw new RuntimeException('enricher boom');
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    $entryLevel = DB::transactionLevel();
    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    DB::transaction(function () use ($order): void {
        User::query()->create([
            'name' => 'Host Writer',
            'email' => 'host-writer@example.com',
            'password' => 'secret',
        ]);

        Event::dispatch(new OrderPaid($order, 'txn-marker-1', 'stripe', 5000));
    });

    $failures = reportedFailures();

    expect(User::query()->where('email', 'host-writer@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'recording-marker@example.com')->exists())->toBeFalse()
        ->and($probe->seenOwner?->getKey())->toBe($ownerB->getKey())
        ->and(OwnerContext::resolve()?->getKey())->toBe($ownerA->getKey())
        ->and(DB::transactionLevel())->toBe($entryLevel)
        ->and($laterRan)->toBeTrue()
        ->and($failures)->toHaveCount(1)
        ->and($failures[0]->context())->toMatchArray(['phase' => 'recording'])
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('refuses to swallow a failure after the connection is replaced mid-attempt', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Disconnect Property',
        'slug' => 'disconnect-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $order = new Order;
    $order->forceFill([
        'grand_total' => 5000,
        'paid_at' => now(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $probe = new class
    {
        public bool $disconnected = false;

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            // Simulate a replaced connection mid-attempt: reconnecting to
            // :memory: SQLite yields a fresh empty database while
            // bookkeeping still claims the outer transaction is open.
            DB::disconnect();
            $this->disconnected = true;

            return $properties;
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    try {
        DB::transaction(function () use ($order): void {
            Event::dispatch(new OrderPaid($order, 'txn-disc-1', 'stripe', 5000));
        });

        $this->fail('The listener should have refused to swallow the failure.');
    } catch (CommerceSignalRecordingFailed $e) {
        expect($e->context())->toMatchArray([
            'source_event_class' => OrderPaid::class,
            'recorder_method' => 'recordOrderPaid',
            'phase' => 'recording',
        ])->and($e->context())->toHaveKeys([
            'entry_transaction_level',
            'exit_transaction_level',
            'entry_server_transaction',
            'exit_server_transaction',
            'entry_connection_id',
            'exit_connection_id',
        ])->and($e->getPrevious())->toBeInstanceOf(CommerceSignalRecordingFailed::class);
    }

    expect($probe->disconnected)->toBeTrue()
        ->and(reportedFailures())->toHaveCount(1);
});

function ingestConversionForAlerts(string $code): ?SignalEvent
{
    $owner = User::query()->firstOrFail();

    $affiliate = Affiliate::query()->create([
        'code' => $code,
        'name' => 'Alert Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
    $affiliate->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    $conversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'value_minor' => 12000,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
    $conversion->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    return app(CommerceSignalsRecorder::class)->recordAffiliateConversionRecorded(
        AffiliateConversionData::fromModel($conversion)
    );
}

function isolationConcurrencyFailure(): QueryException
{
    $pdo = new PDOException('could not serialize access due to concurrent update');

    // Real PDO drivers carry string SQLSTATE codes internally.
    (new ReflectionProperty(PDOException::class, 'code'))->setValue($pdo, '40001');

    return new QueryException('sqlite', 'update "probe" set "name" = ?', ['probe'], $pdo);
}

function rollbackTestingTransactionToZero(): User
{
    // Reach a real transaction level zero: RefreshDatabase wraps every
    // test in a transaction, so the listener's no-outer branch and a
    // retryable host transaction are only reachable after rolling it
    // back. Package-migration schema was committed before the testing
    // transaction and survives; rows and tables created inside it do
    // not, so the users fixture is rebuilt. Only use this in tests with
    // no afterCommit dependencies: the testing manager still counts the
    // rolled-back wrapping transaction as applicable.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    $owner = User::query()->create([
        'name' => 'Level Zero Owner',
        'email' => 'level-zero-owner@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    return $owner;
}

function isolationOrder(Model $owner): Order
{
    $order = new Order;
    $order->forceFill([
        'grand_total' => 5000,
        'paid_at' => now(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    return $order;
}

it('propagates marked transaction-control failures unreported inside an outer transaction', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Marker Property',
        'slug' => 'marker-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $marker = CommerceSignalTransactionControlFailed::fromControlFailure(new RuntimeException('savepoint boom'));

    $probe = new class($marker)
    {
        public function __construct(private readonly CommerceSignalTransactionControlFailed $marker) {}

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            throw $this->marker;
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    $entryLevel = DB::transactionLevel();

    try {
        DB::transaction(function (): void {
            User::query()->create([
                'name' => 'Marker Host Writer',
                'email' => 'marker-host-writer@example.com',
                'password' => 'secret',
            ]);

            Event::dispatch(new OrderPaid(isolationOrder(User::query()->firstOrFail()), 'txn-marker-outer-1', 'stripe', 5000));
        });

        $this->fail('The marked failure should have propagated.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e)->toBe($marker);
    }

    expect(reportedFailures())->toHaveCount(0)
        ->and(User::query()->where('email', 'marker-host-writer@example.com')->exists())->toBeFalse()
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe($entryLevel);
});

it('propagates marked failures unreported without an outer transaction', function (): void {
    $owner = rollbackTestingTransactionToZero();

    expect(DB::transactionLevel())->toBe(0);

    TrackedProperty::query()->create([
        'name' => 'Marker No Outer Property',
        'slug' => 'marker-no-outer-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $marker = CommerceSignalTransactionControlFailed::fromControlFailure(new RuntimeException('begin boom'));

    $probe = new class($marker)
    {
        public function __construct(private readonly CommerceSignalTransactionControlFailed $marker) {}

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            throw $this->marker;
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    try {
        Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-marker-no-outer-1', 'stripe', 5000));

        $this->fail('The marked failure should have propagated.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e)->toBe($marker);
    }

    expect(reportedFailures())->toHaveCount(0)
        ->and($laterRan)->toBeFalse()
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe(0);
});

it('retries the host transaction without intermediate reports on concurrency', function (): void {
    $owner = rollbackTestingTransactionToZero();

    TrackedProperty::query()->create([
        'name' => 'Concurrency Retry Property',
        'slug' => 'concurrency-retry-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $probe = new class
    {
        public int $calls = 0;

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            $this->calls++;

            if ($this->calls === 1) {
                throw isolationConcurrencyFailure();
            }

            return $properties;
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    $attempts = 0;

    DB::transaction(function () use ($owner, &$attempts): void {
        $attempts++;

        Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-conc-retry-1', 'stripe', 5000));
    }, 3);

    expect($attempts)->toBe(2)
        ->and($probe->calls)->toBe(2)
        ->and(SignalEvent::query()->withoutOwnerScope()->sole()->event_name)->toBe('order.paid')
        ->and(reportedFailures())->toHaveCount(0)
        ->and($laterRan)->toBeTrue()
        ->and(DB::transactionLevel())->toBe(0);
});

it('reports once and continues when no-outer retries are exhausted', function (): void {
    $owner = rollbackTestingTransactionToZero();

    TrackedProperty::query()->create([
        'name' => 'Exhaustion Property',
        'slug' => 'exhaustion-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $invocations = 0;

    SignalEvent::saving(function () use (&$invocations): void {
        $invocations++;

        throw isolationConcurrencyFailure();
    });

    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-exhaust-1', 'stripe', 5000));

    $failures = reportedFailures();

    expect($invocations)->toBe(5)
        ->and($failures)->toHaveCount(1)
        ->and($failures[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and($failures[0]->getPrevious())->toBeInstanceOf(QueryException::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and($laterRan)->toBeTrue()
        ->and(DB::transactionLevel())->toBe(0);
});

it('marks deeper savepoint creation failures and propagates them unreported', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Savepoint Creation Property',
        'slug' => 'savepoint-creation-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $entryLevel = DB::transactionLevel();
    $hookFailure = new RuntimeException('savepoint boom');
    $hookFired = false;

    // Target the recording-owned transaction begin: the test and
    // listener savepoints begin at or below the entry level plus one.
    DB::beforeStartingTransaction(function () use ($entryLevel, $hookFailure, &$hookFired): void {
        if (DB::transactionLevel() <= $entryLevel + 1) {
            return;
        }

        $hookFired = true;

        throw $hookFailure;
    });

    try {
        DB::transaction(function () use ($owner): void {
            Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-sp-create-1', 'stripe', 5000));
        });

        $this->fail('The savepoint failure should have propagated.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e->getPrevious())->toBe($hookFailure)
            ->and($e->context())->not->toHaveKey('callback_failure_class');
    }

    expect($hookFired)->toBeTrue()
        ->and(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe($entryLevel);
});

it('marks recording commit failures when server state diverges', function (): void {
    $owner = rollbackTestingTransactionToZero();

    TrackedProperty::query()->create([
        'name' => 'Commit Divergence Property',
        'slug' => 'commit-divergence-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    SignalEvent::saved(function (): void {
        // Roll the server transaction back behind Laravel's back: the
        // callback completes, then the commit fails on a connection
        // with no active server transaction. Unlike a failed rollback,
        // the failed commit still unwinds the bookkeeping, so the
        // marker reaches the caller instead of cascading.
        DB::getPdo()->rollBack();
    });

    try {
        Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-commit-diverge-1', 'stripe', 5000));

        $this->fail('The commit failure should have propagated.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e->getPrevious())->toBeInstanceOf(PDOException::class)
            ->and($e->context())->toMatchArray(['control_failure_class' => PDOException::class])
            ->and($e->context())->not->toHaveKey('callback_failure_class');
    }

    expect(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe(0);
});

it('propagates listener rollback failures raw when the savepoint is gone', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Rollback Cascade Property',
        'slug' => 'rollback-cascade-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $entryLevel = DB::transactionLevel();

    $probe = new class
    {
        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            // Release the listener's own savepoint, then fail: the
            // listener rollback masks the recording failure, and the
            // stuck level makes the test rollback miss the same way.
            // Failed rollbacks skip level assignment, so a cascade
            // masks everything beneath it — the raw infrastructure
            // failure is the honest outcome. The savepoint name follows
            // Laravel's trans{N} convention.
            DB::statement('RELEASE trans' . DB::transactionLevel());

            throw new RuntimeException('recording boom');
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    try {
        DB::transaction(function () use ($owner): void {
            Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-rb-cascade-1', 'stripe', 5000));
        });

        $this->fail('The rollback failure should have propagated.');
    } catch (PDOException $e) {
        expect($e->getMessage())->toContain('no such savepoint');
    }

    // The cascade leaves the bookkeeping stuck: recreate the savepoint
    // so teardown can unwind the testing transaction.
    DB::statement('SAVEPOINT trans' . ($entryLevel + 2));

    expect(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('propagates listener savepoint creation failures raw and unreported', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Listener Savepoint Property',
        'slug' => 'listener-savepoint-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $entryLevel = DB::transactionLevel();
    $hookFailure = new RuntimeException('listener savepoint boom');
    $hookFired = false;

    DB::beforeStartingTransaction(function () use ($entryLevel, $hookFailure, &$hookFired): void {
        if (DB::transactionLevel() <= $entryLevel) {
            return;
        }

        $hookFired = true;

        throw $hookFailure;
    });

    try {
        DB::transaction(function () use ($owner): void {
            Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-lsp-1', 'stripe', 5000));
        });

        $this->fail('The savepoint failure should have propagated.');
    } catch (RuntimeException $e) {
        expect($e)->toBe($hookFailure);
    }

    expect($hookFired)->toBeTrue()
        ->and(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe($entryLevel);
});

it('propagates control-originated lost connections unreported without an outer transaction', function (): void {
    $owner = rollbackTestingTransactionToZero();

    expect(DB::transactionLevel())->toBe(0);

    TrackedProperty::query()->create([
        'name' => 'Marker Lost No Outer Property',
        'slug' => 'marker-lost-no-outer-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    // Control provenance beats recognition: a lost connection that
    // escapes recording-owned machinery differing from the callback
    // failure arrives marked, so the no-outer branch propagates it
    // unreported instead of swallowing it as an exhausted retry.
    $lost = new QueryException('sqlite', 'ROLLBACK', [], new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'));
    $marker = CommerceSignalTransactionControlFailed::fromControlFailure($lost, new RuntimeException('callback boom'));

    $probe = new class($marker)
    {
        public function __construct(private readonly CommerceSignalTransactionControlFailed $marker) {}

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            throw $this->marker;
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    try {
        Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-marker-lost-no-outer-1', 'stripe', 5000));

        $this->fail('The marked failure should have propagated.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e)->toBe($marker);
    }

    expect(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe(0);
});

it('marks a lost connection thrown from rollback during ingestion', function (): void {
    $owner = rollbackTestingTransactionToZero();

    expect(DB::transactionLevel())->toBe(0);

    TrackedProperty::query()->create([
        'name' => 'Lost Rollback Property',
        'slug' => 'lost-rollback-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    // A lost connection during rollback escapes as a different object
    // than the callback failure, so provenance marks it even though
    // the failure itself is recognized. Laravel resets the bookkeeping
    // to zero for lost rollbacks, which is what makes the restored
    // state meaningful here.
    $lost = new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
    $callbackFired = false;

    SignalEvent::saving(function () use (&$callbackFired): void {
        $callbackFired = true;

        throw new LogicException('callback boom');
    });

    $real = DB::getPdo();

    $spy = new class($real, $lost) extends PDO
    {
        public int $rollbackCalls = 0;

        public function __construct(private readonly PDO $real, private readonly Throwable $rollbackFailure)
        {
            parent::__construct('sqlite::memory:');
        }

        public function beginTransaction(): bool
        {
            return $this->real->beginTransaction();
        }

        public function commit(): bool
        {
            return $this->real->commit();
        }

        public function rollBack(): bool
        {
            $this->rollbackCalls++;

            $this->real->rollBack();

            throw $this->rollbackFailure;
        }

        public function inTransaction(): bool
        {
            return $this->real->inTransaction();
        }

        public function exec(string $statement): int | false
        {
            return $this->real->exec($statement);
        }

        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement | false
        {
            return $this->real->query($query, $fetchMode, ...$fetchModeArgs);
        }

        public function prepare(string $query, array $options = []): PDOStatement | false
        {
            return $this->real->prepare($query, $options);
        }

        public function lastInsertId(?string $name = null): string | false
        {
            return $this->real->lastInsertId($name);
        }
    };

    DB::connection()->setPdo($spy);

    try {
        Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-lost-rollback-1', 'stripe', 5000));

        $this->fail('The marked failure should have propagated.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e->getPrevious())->toBe($lost)
            ->and($e->context())->toMatchArray([
                'control_failure_class' => RuntimeException::class,
                'callback_failure_class' => LogicException::class,
            ])
            // The bookkeeping is fully restored — level zero, no server
            // transaction, same connection — yet the marked failure
            // still propagates instead of being swallowed.
            ->and(DB::transactionLevel())->toBe(0)
            ->and(DB::getPdo())->toBe($spy)
            ->and(DB::getPdo()->inTransaction())->toBeFalse();
    } finally {
        DB::connection()->setPdo($real);
    }

    expect($callbackFired)->toBeTrue()
        ->and($spy->rollbackCalls)->toBe(1)
        ->and(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe(0)
        ->and($real->inTransaction())->toBeFalse();
});

function useIsolatedWeakConnection(): void
{
    // A dynamically-defined connection never enters
    // RefreshDatabaseState::$inMemoryConnections, so its PDO has no
    // ambient strong references and WeakReference observations prove
    // listener retention instead of framework retention.
    config()->set('database.connections.testing_weak', config('database.connections.testing'));

    DB::purge('testing_weak');
    DB::setDefaultConnection('testing_weak');

    Schema::create(config('signals.database.tables.tracked_properties', 'signal_tracked_properties'), function (Blueprint $table): void {
        $jsonColumnType = commerce_json_column_type('signals', 'jsonb');

        $table->uuid('id')->primary();
        $table->nullableUuidMorphs('owner');
        $table->string('owner_scope')->default('global');
        $table->string('name');
        $table->string('slug');
        $table->string('write_key')->unique();
        $table->string('domain')->nullable();
        $table->string('type')->default(config('signals.defaults.property_type', 'website'));
        $table->string('timezone')->default(config('signals.defaults.timezone', 'UTC'));
        $table->string('currency', 3)->default(config('signals.defaults.currency', 'MYR'));
        $table->boolean('is_active')->default(true);
        $table->{$jsonColumnType}('settings')->nullable();
        $table->timestampsTz();

        $table->unique(['owner_scope', 'slug']);
    });
}

function restoreDefaultConnection(): void
{
    DB::setDefaultConnection('testing');
}

it('detects a released connection replaced with matching nesting and presence', function (): void {
    $owner = User::query()->firstOrFail();

    useIsolatedWeakConnection();

    expect(RefreshDatabaseState::$inMemoryConnections)->not->toHaveKey('testing_weak');

    try {
        // Direct insert: model events (activity log, auditing) are
        // incidental here and their tables exist only on the testing
        // connection.
        DB::table(config('signals.database.tables.tracked_properties', 'signal_tracked_properties'))->insert([
            'id' => (string) str()->uuid(),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'name' => 'Replaced Connection Property',
            'slug' => 'replaced-connection-property',
            'write_key' => str()->random(40),
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $probe = new class
        {
            public ?int $entryConnectionId = null;

            public ?int $exitConnectionId = null;

            public bool $swapped = false;

            public bool $entrySurvivedDisconnect = false;

            public function handle(object $source, TrackedProperty $property, array $properties): array
            {
                if (! $this->swapped) {
                    // No fixture-owned strong reference: the weak
                    // reference observes whether the listener's retained
                    // PDO keeps the entry connection alive across the
                    // disconnect. A garbage collection pass makes the
                    // observation deterministic even if the PDO
                    // participates in reference cycles.
                    $entry = WeakReference::create(DB::getPdo());
                    $this->entryConnectionId = spl_object_id(DB::getPdo());

                    $connection = DB::connection();
                    $connection->disconnect();
                    gc_collect_cycles();

                    $replacement = new PDO('sqlite::memory:');
                    $connection->setPdo($replacement);
                    $this->exitConnectionId = spl_object_id($replacement);
                    $this->entrySurvivedDisconnect = $entry->get() !== null;
                    $this->swapped = true;
                }

                return $properties;
            }
        };

        app()->instance('growth.signal_event_property_enricher', $probe);

        try {
            // Explicit owner context: enrichment must not query the
            // users table, which exists only on the testing connection.
            $order = isolationOrder($owner);
            $order->setRelation('owner', $owner);

            OwnerContext::withOwner($owner, fn (): mixed => Event::dispatch(new OrderPaid($order, 'txn-replaced-conn-1', 'stripe', 5000)));

            $this->fail('The listener should have refused to swallow the failure.');
        } catch (CommerceSignalRecordingFailed $e) {
            expect($e->context())->toMatchArray([
                'source_event_class' => OrderPaid::class,
                'recorder_method' => 'recordOrderPaid',
                'phase' => 'recording',
                // Only the connection leg fires: nesting and presence
                // match exactly, so an integer-ID guard would swallow
                // this once the replacement reuses the entry ID.
                'entry_transaction_level' => 0,
                'exit_transaction_level' => 0,
                'entry_server_transaction' => false,
                'exit_server_transaction' => false,
            ])->and($e->context())->toHaveKeys([
                'entry_connection_id',
                'exit_connection_id',
            ])->and($e->getPrevious())->toBeInstanceOf(CommerceSignalRecordingFailed::class);
        }

        // The entry PDO survived the disconnect because the listener
        // retained it, so the replacement could not reuse its object
        // ID. (No database assertions: the connection now points at a
        // fresh empty database and the entry connection is gone.)
        expect($probe->swapped)->toBeTrue()
            ->and($probe->entrySurvivedDisconnect)->toBeTrue()
            ->and($probe->entryConnectionId)->not->toBeNull()
            ->and($probe->exitConnectionId)->not->toBeNull()
            ->and($probe->exitConnectionId)->not->toBe($probe->entryConnectionId)
            ->and(reportedFailures())->toHaveCount(1)
            ->and(DB::transactionLevel())->toBe(0);
    } finally {
        restoreDefaultConnection();
    }
});

it('detects a replaced connection inside an outer transaction by instance', function (): void {
    $owner = User::query()->firstOrFail();

    useIsolatedWeakConnection();

    expect(RefreshDatabaseState::$inMemoryConnections)->not->toHaveKey('testing_weak');

    DB::beginTransaction();

    try {
        DB::table(config('signals.database.tables.tracked_properties', 'signal_tracked_properties'))->insert([
            'id' => (string) str()->uuid(),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'name' => 'Replaced Outer Connection Property',
            'slug' => 'replaced-outer-connection-property',
            'write_key' => str()->random(40),
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $probe = new class
        {
            public bool $swapped = false;

            public bool $entrySurvivedDisconnect = false;

            public function handle(object $source, TrackedProperty $property, array $properties): array
            {
                if (! $this->swapped) {
                    // No fixture-owned strong reference: the weak
                    // reference observes whether the listener's retained
                    // PDO keeps the entry connection alive across the
                    // disconnect.
                    $entry = WeakReference::create(DB::getPdo());
                    $depth = DB::transactionLevel();

                    // Disconnecting resets the level counter to zero, so
                    // rebuild matching nesting and presence on the
                    // replacement with public API: only the connection
                    // leg fires at the guard.
                    $connection = DB::connection();
                    $connection->disconnect();
                    gc_collect_cycles();
                    $connection->setPdo(new PDO('sqlite::memory:'));

                    for ($i = 0; $i < $depth; $i++) {
                        DB::beginTransaction();
                    }

                    $this->entrySurvivedDisconnect = $entry->get() !== null;
                    $this->swapped = true;
                }

                return $properties;
            }
        };

        app()->instance('growth.signal_event_property_enricher', $probe);

        try {
            // Explicit owner context: enrichment must not query the
            // users table, which exists only on the testing connection.
            // No host-transaction wrapper either, so no rollback
            // cascade can mask the guard failure.
            $order = isolationOrder($owner);
            $order->setRelation('owner', $owner);

            OwnerContext::withOwner($owner, fn (): mixed => Event::dispatch(new OrderPaid($order, 'txn-replaced-outer-conn-1', 'stripe', 5000)));

            $this->fail('The listener should have refused to swallow the failure.');
        } catch (CommerceSignalRecordingFailed $e) {
            expect($e->context())->toMatchArray([
                'source_event_class' => OrderPaid::class,
                'recorder_method' => 'recordOrderPaid',
                'phase' => 'recording',
                'entry_transaction_level' => 1,
                'exit_transaction_level' => 1,
                'entry_server_transaction' => true,
                'exit_server_transaction' => true,
            ])->and($e->context())->toHaveKeys([
                'entry_connection_id',
                'exit_connection_id',
            ])->and($e->getPrevious())->toBeInstanceOf(CommerceSignalRecordingFailed::class);
        }

        expect($probe->swapped)->toBeTrue()
            ->and($probe->entrySurvivedDisconnect)->toBeTrue()
            ->and(reportedFailures())->toHaveCount(1)
            ->and(DB::transactionLevel())->toBe(1);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        restoreDefaultConnection();
    }
});

it('propagates lost connections raw and unreported inside an outer transaction', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Lost Connection Property',
        'slug' => 'lost-connection-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $lost = new QueryException('sqlite', 'select * from "probe"', [], new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'));

    $probe = new class($lost)
    {
        public function __construct(private readonly QueryException $lost) {}

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            throw $this->lost;
        }
    };

    app()->instance('growth.signal_event_property_enricher', $probe);

    $entryLevel = DB::transactionLevel();

    try {
        DB::transaction(function () use ($owner): void {
            Event::dispatch(new OrderPaid(isolationOrder($owner), 'txn-lost-1', 'stripe', 5000));
        });

        $this->fail('The lost connection should have propagated.');
    } catch (QueryException $e) {
        expect($e)->toBe($lost);
    }

    expect(reportedFailures())->toHaveCount(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe($entryLevel);
});
