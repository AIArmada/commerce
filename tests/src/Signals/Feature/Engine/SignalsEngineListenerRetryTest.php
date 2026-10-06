<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Exceptions\CommerceSignalRecordingFailed;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(SignalsEngineTestCase::class);

function engineConcurrencyFailure(): QueryException
{
    $pdo = new PDOException('could not serialize access due to concurrent update');

    // Real PDO drivers carry string SQLSTATE codes internally.
    (new ReflectionProperty(PDOException::class, 'code'))->setValue($pdo, '40001');

    return new QueryException('engine', 'update "probe" set "name" = ?', ['probe'], $pdo);
}

it('retries the host transaction when recording hits a serialization failure', function (string $engine): void {
    $this->useEngine($engine);
    $this->setEngineSessionIsolation('REPEATABLE READ');

    $property = $this->engineProperty('Engine Listener Retry');
    $propertyId = (string) $property->id;
    $propertyTable = (new TrackedProperty)->getTable();

    $order = new Order;
    $order->forceFill([
        'grand_total' => 5000,
        'paid_at' => now(),
        'owner_type' => $this->engineOwner->getMorphClass(),
        'owner_id' => $this->engineOwner->getKey(),
    ]);

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

    // Conflict injector: touches the property row during recording. On the
    // first host attempt the row was concurrently committed by the second
    // connection, so PostgreSQL raises SQLSTATE 40001 under REPEATABLE
    // READ. The listener must propagate it unwrapped so the host retries.
    // MySQL needs its own conflict scenario; this dataset stays
    // PostgreSQL-only.
    app()->instance('growth.signal_event_property_enricher', new class($propertyId)
    {
        public function __construct(private readonly string $propertyId) {}

        public function handle(object $source, TrackedProperty $property, array $properties): array
        {
            TrackedProperty::query()->withoutOwnerScope()->whereKey($this->propertyId)->update([
                'name' => 'Engine Listener Retry Touched',
            ]);

            return $properties;
        }
    });

    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    $attempts = 0;
    $secondConnection = $this->engineSecondConnection;

    DB::transaction(function () use ($order, $propertyId, $propertyTable, $secondConnection, &$attempts): void {
        $attempts++;

        // Establish this attempt snapshot before the concurrent commit.
        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId);

        if ($attempts === 1) {
            DB::connection($secondConnection)->table($propertyTable)->where('id', $propertyId)->update([
                'name' => 'Engine Listener Retry Concurrent',
            ]);
        }

        Event::dispatch(new OrderPaid($order, 'txn-engine-retry-1', 'stripe', 5000));
    }, 3);

    $events = SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();

    expect($attempts)->toBe(2)
        ->and($events)->toHaveCount(1)
        ->and($events->first()->event_name)->toBe('order.paid')
        ->and(app(ExceptionHandler::class)->reported)->toHaveCount(0)
        ->and($laterRan)->toBeTrue();
})->with(['pgsql']);

it('reports once and continues when recording retries are exhausted without an outer transaction', function (string $engine): void {
    $this->useEngine($engine);

    expect(DB::transactionLevel())->toBe(0);

    $property = $this->engineProperty('Engine Listener Exhaustion');
    $propertyId = (string) $property->id;

    $order = new Order;
    $order->forceFill([
        'grand_total' => 5000,
        'paid_at' => now(),
        'owner_type' => $this->engineOwner->getMorphClass(),
        'owner_id' => $this->engineOwner->getKey(),
    ]);

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

    $invocations = 0;

    SignalEvent::saving(function () use (&$invocations): void {
        $invocations++;

        throw engineConcurrencyFailure();
    });

    $laterRan = false;

    Event::listen(OrderPaid::class, function () use (&$laterRan): void {
        $laterRan = true;
    });

    Event::dispatch(new OrderPaid($order, 'txn-engine-exhaust-1', 'stripe', 5000));

    $reported = app(ExceptionHandler::class)->reported;

    expect($invocations)->toBe(5)
        ->and($reported)->toHaveCount(1)
        ->and($reported[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and($reported[0]->getPrevious())->toBeInstanceOf(QueryException::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->count())->toBe(0)
        ->and($laterRan)->toBeTrue()
        ->and(DB::transactionLevel())->toBe(0);
})->with(['pgsql', 'mysql']);
