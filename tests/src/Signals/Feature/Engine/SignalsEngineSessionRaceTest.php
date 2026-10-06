<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Commerce\Tests\Signals\Support\EngineRace;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Actions\ResolveSession;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(SignalsEngineTestCase::class);

function engineSessionRepeats(): int
{
    return 5;
}

/**
 * Child-local session-insert rendezvous: both creators must reach the
 * session creating hook for the shared identifier after both absent-row
 * probes before either proceeds to INSERT, forcing a genuine unique
 * collision instead of a start-barrier-only overlap claim.
 */
function engineSessionRendezvous(string $workdir, string $role, string $peer, string $sharedIdentifier): void
{
    SignalSession::creating(static function (SignalSession $model) use ($workdir, $role, $peer, $sharedIdentifier): bool {
        if ($model->session_identifier !== $sharedIdentifier) {
            return true;
        }

        file_put_contents($workdir . '/session-ready-' . $role, (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($workdir . '/session-ready-' . $peer)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('session insert rendezvous timed out waiting for [' . $peer . '].');
            }

            usleep(1000);
        }

        return true;
    });
}

it('retains the latest session end under genuine concurrent creation and update', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineSessionRepeats(); $round++) {
        $property = $this->engineProperty('Engine Session Race');
        $propertyId = (string) $property->id;
        $sessionIdentifier = 'eng-session-' . Str::lower(Str::random(10));

        $payloads = [
            'earlier' => [
                'event_name' => 'custom.session-race',
                'anonymous_id' => 'eng-session-anon-a-' . Str::lower(Str::random(6)),
                'session_identifier' => $sessionIdentifier,
                'session_started_at' => '2026-10-04T10:00:00Z',
                'occurred_at' => '2026-10-04T10:00:00Z',
                'path' => '/earlier',
                'idempotency_key' => 'eng-session-a-' . Str::lower(Str::random(10)),
            ],
            'later' => [
                'event_name' => 'custom.session-race',
                'anonymous_id' => 'eng-session-anon-b-' . Str::lower(Str::random(6)),
                'session_identifier' => $sessionIdentifier,
                'session_started_at' => '2026-10-04T10:00:00Z',
                'occurred_at' => '2026-10-04T10:10:00Z',
                'path' => '/later',
                'idempotency_key' => 'eng-session-b-' . Str::lower(Str::random(10)),
            ],
        ];

        // True simultaneous race on separate connections: both workers reach
        // creating after both absent-row probes before either INSERTs; the
        // loser recovers onto the current winner session.
        $results = EngineRace::run([
            'earlier' => static function (string $workdir) use ($propertyId, $payloads, $sessionIdentifier): array {
                engineSessionRendezvous($workdir, 'earlier', 'later', $sessionIdentifier);

                return [
                    'event_id' => (string) app(IngestSignalEvent::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $payloads['earlier'],
                        true
                    )->id,
                    'barrier' => [
                        'reached' => is_file($workdir . '/session-ready-earlier'),
                        'peer_seen' => is_file($workdir . '/session-ready-later'),
                    ],
                ];
            },
            'later' => static function (string $workdir) use ($propertyId, $payloads, $sessionIdentifier): array {
                engineSessionRendezvous($workdir, 'later', 'earlier', $sessionIdentifier);

                return [
                    'event_id' => (string) app(IngestSignalEvent::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $payloads['later'],
                        true
                    )->id,
                    'barrier' => [
                        'reached' => is_file($workdir . '/session-ready-later'),
                        'peer_seen' => is_file($workdir . '/session-ready-earlier'),
                    ],
                ];
            },
        ]);

        expect($results['earlier']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['later']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['earlier']['event_id'])->not->toBe($results['later']['event_id']);

        $sessions = SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();

        expect($sessions)->toHaveCount(1)
            ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->count())->toBe(2);

        $session = $sessions->sole();

        expect($session->ended_at?->toAtomString())->toBe('2026-10-04T10:10:00+00:00')
            ->and($session->exit_path)->toBe('/later')
            ->and($session->duration_milliseconds)->toBe(600000)
            ->and($session->bounced_at)->toBeNull();
    }
})->with(['pgsql', 'mysql']);

it('retains the latest session end under genuine concurrent creation without identities', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineSessionRepeats(); $round++) {
        $property = $this->engineProperty('Engine Session NoIdent');
        $propertyId = (string) $property->id;
        $sessionIdentifier = 'eng-session-noident-' . Str::lower(Str::random(10));

        $payloads = [
            'earlier' => [
                'event_name' => 'custom.session-race',
                'session_identifier' => $sessionIdentifier,
                'session_started_at' => '2026-10-04T10:00:00Z',
                'occurred_at' => '2026-10-04T10:00:00Z',
                'path' => '/earlier',
                'idempotency_key' => 'eng-session-noident-a-' . Str::lower(Str::random(10)),
            ],
            'later' => [
                'event_name' => 'custom.session-race',
                'session_identifier' => $sessionIdentifier,
                'session_started_at' => '2026-10-04T10:00:00Z',
                'occurred_at' => '2026-10-04T10:10:00Z',
                'path' => '/later',
                'idempotency_key' => 'eng-session-noident-b-' . Str::lower(Str::random(10)),
            ],
        ];

        $results = EngineRace::run([
            'earlier' => static function (string $workdir) use ($propertyId, $payloads, $sessionIdentifier): array {
                engineSessionRendezvous($workdir, 'earlier', 'later', $sessionIdentifier);

                return [
                    'event_id' => (string) app(IngestSignalEvent::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $payloads['earlier'],
                        true
                    )->id,
                    'barrier' => [
                        'reached' => is_file($workdir . '/session-ready-earlier'),
                        'peer_seen' => is_file($workdir . '/session-ready-later'),
                    ],
                ];
            },
            'later' => static function (string $workdir) use ($propertyId, $payloads, $sessionIdentifier): array {
                engineSessionRendezvous($workdir, 'later', 'earlier', $sessionIdentifier);

                return [
                    'event_id' => (string) app(IngestSignalEvent::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $payloads['later'],
                        true
                    )->id,
                    'barrier' => [
                        'reached' => is_file($workdir . '/session-ready-later'),
                        'peer_seen' => is_file($workdir . '/session-ready-earlier'),
                    ],
                ];
            },
        ]);

        expect($results['earlier']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['later']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['earlier']['event_id'])->not->toBe($results['later']['event_id']);

        $sessions = SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();

        expect($sessions)->toHaveCount(1)
            ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->count())->toBe(2);

        $session = $sessions->sole();

        expect($session->ended_at?->toAtomString())->toBe('2026-10-04T10:10:00+00:00')
            ->and($session->exit_path)->toBe('/later')
            ->and($session->duration_milliseconds)->toBe(600000)
            ->and($session->bounced_at)->toBeNull()
            ->and($session->signal_identity_id)->toBeNull();
    }
})->with(['pgsql', 'mysql']);

it('serializes overlapping session transactions in deterministic lock order', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineSessionRepeats(); $round++) {
        $property = $this->engineProperty('Engine Session Overlap');
        $propertyId = (string) $property->id;
        $sessionIdentifier = 'eng-session-overlap-' . Str::lower(Str::random(10));

        app(ResolveSession::class)->handle($property, null, [
            'session_identifier' => $sessionIdentifier,
            'session_started_at' => '2026-10-04T10:00:00Z',
        ]);

        $earlierPayload = [
            'event_name' => 'custom.session-overlap',
            'anonymous_id' => 'eng-overlap-anon-a-' . Str::lower(Str::random(6)),
            'session_identifier' => $sessionIdentifier,
            'session_started_at' => '2026-10-04T10:00:00Z',
            'occurred_at' => '2026-10-04T10:00:00Z',
            'path' => '/earlier',
            'idempotency_key' => 'eng-overlap-a-' . Str::lower(Str::random(10)),
        ];
        $laterPayload = [
            'event_name' => 'custom.session-overlap',
            'anonymous_id' => 'eng-overlap-anon-b-' . Str::lower(Str::random(6)),
            'session_identifier' => $sessionIdentifier,
            'session_started_at' => '2026-10-04T10:00:00Z',
            'occurred_at' => '2026-10-04T10:10:00Z',
            'path' => '/later',
            'idempotency_key' => 'eng-overlap-b-' . Str::lower(Str::random(10)),
        ];

        // Deterministic overlapping-update scenario on separate connections:
        // the session is precreated committed and empty, the holder ingests
        // its first event inside an open outer transaction and retains the
        // session row lock, and the updater marks its attempt from inside its
        // own transaction at the session locking SELECT via beforeExecuting.
        // The holder commits only after that marker, so the updater snapshot
        // is established before the holder commit and its bounce check must
        // use a current (locking) read to clear bounced_at.
        $results = EngineRace::run([
            'holder' => static function (string $workdir) use ($propertyId, $earlierPayload): array {
                DB::beginTransaction();

                try {
                    $eventId = (string) app(IngestSignalEvent::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $earlierPayload,
                        true
                    )->id;

                    file_put_contents($workdir . '/overlap-holding', (string) getmypid());

                    $deadline = microtime(true) + 60;

                    while (! is_file($workdir . '/overlap-attempting')) {
                        if (microtime(true) > $deadline) {
                            throw new RuntimeException('overlap holder timed out waiting for the updater attempt.');
                        }

                        usleep(1000);
                    }

                    DB::commit();

                    return ['event_id' => $eventId, 'holding_released' => true];
                } catch (Throwable $e) {
                    DB::rollBack();

                    throw $e;
                }
            },
            'updater' => static function (string $workdir) use ($propertyId, $laterPayload, $sessionIdentifier): array {
                $deadline = microtime(true) + 60;

                while (! is_file($workdir . '/overlap-holding')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('overlap updater timed out waiting for the open holder transaction.');
                    }

                    usleep(1000);
                }

                $sessionTable = (new SignalSession)->getTable();

                DB::connection()->beforeExecuting(static function (string $query, array $bindings) use ($workdir, $sessionTable, $sessionIdentifier): void {
                    if (is_file($workdir . '/overlap-attempting')) {
                        return;
                    }

                    if (! str_contains($query, $sessionTable)) {
                        return;
                    }

                    if (! str_contains(mb_strtolower($query), 'for update')) {
                        return;
                    }

                    if (! in_array($sessionIdentifier, $bindings, true)) {
                        return;
                    }

                    file_put_contents($workdir . '/overlap-attempting', (string) getmypid());
                });

                $eventId = (string) app(IngestSignalEvent::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    $laterPayload,
                    true
                )->id;

                return ['event_id' => $eventId, 'saw_holding' => true, 'attempt_marked' => is_file($workdir . '/overlap-attempting')];
            },
        ]);

        expect($results['holder']['holding_released'])->toBeTrue()
            ->and($results['updater']['saw_holding'])->toBeTrue()
            ->and($results['updater']['attempt_marked'])->toBeTrue()
            ->and($results['holder']['event_id'])->not->toBe($results['updater']['event_id']);

        $sessions = SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();

        expect($sessions)->toHaveCount(1)
            ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->count())->toBe(2);

        $session = $sessions->sole();

        expect($session->ended_at?->toAtomString())->toBe('2026-10-04T10:10:00+00:00')
            ->and($session->exit_path)->toBe('/later')
            ->and($session->duration_milliseconds)->toBe(600000)
            ->and($session->bounced_at)->toBeNull();
    }
})->with(['pgsql', 'mysql']);

it('keeps monotonic session ends for sequential out-of-order events across two connections', function (string $engine): void {
    $this->useEngine($engine);

    $property = $this->engineProperty('Engine Session Order');
    $propertyId = (string) $property->id;

    // Sequential two-connection scenario (not a race): alternate the
    // default connection per ingest so no shared-transaction artifact can
    // leak between the ordered writes.
    $sessionIdentifier = 'eng-session-order-' . Str::lower(Str::random(6));

    $ingestOn = function (string $occurredAt, string $path, string $connection) use ($propertyId, $sessionIdentifier): SignalEvent {
        config()->set('database.default', $connection);

        try {
            return app(IngestSignalEvent::class)->handle(
                TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                [
                    'event_name' => 'custom.session-order',
                    'session_identifier' => $sessionIdentifier,
                    'session_started_at' => '2026-10-04T12:00:00Z',
                    'occurred_at' => $occurredAt,
                    'path' => $path,
                    'idempotency_key' => 'eng-order-' . Str::lower(Str::random(10)),
                ],
                true
            );
        } finally {
            config()->set('database.default', $this->engineConnection);
        }
    };

    $ingestOn('2026-10-04T12:00:00Z', '/first', $this->engineConnection);
    $ingestOn('2026-10-04T12:10:00Z', '/second', $this->engineSecondConnection);
    $late = $ingestOn('2026-10-04T12:05:00Z', '/late', $this->engineConnection);

    $session = $late->session->refresh();

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->count())->toBe(3)
        ->and($session->ended_at?->toAtomString())->toBe('2026-10-04T12:10:00+00:00')
        ->and($session->exit_path)->toBe('/second')
        ->and($session->duration_milliseconds)->toBe(600000)
        ->and($session->bounced_at)->toBeNull();
})->with(['pgsql', 'mysql']);
