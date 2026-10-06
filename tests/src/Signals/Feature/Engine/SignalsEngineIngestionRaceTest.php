<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Commerce\Tests\Signals\Support\EngineRace;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Str;

uses(SignalsEngineTestCase::class);

function engineIngestionRepeats(): int
{
    return 5;
}

/**
 * Child-local event-insert rendezvous: both workers must reach the event
 * creating hook before either proceeds to INSERT, forcing a genuine
 * idempotency-key collision instead of a start-barrier-only overlap claim.
 */
function engineEventRendezvous(string $workdir, string $role, string $peer): void
{
    SignalEvent::creating(static function () use ($workdir, $role, $peer): bool {
        file_put_contents($workdir . '/event-ready-' . $role, (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($workdir . '/event-ready-' . $peer)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('event insert rendezvous timed out waiting for [' . $peer . '].');
            }

            usleep(1000);
        }

        return true;
    });
}

it('persists exactly one event under genuine duplicate concurrent ingestion', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineIngestionRepeats(); $round++) {
        $property = $this->engineProperty('Engine Ingest Race');
        $propertyId = (string) $property->id;
        $key = 'eng-race-' . Str::lower(Str::random(10));
        $sessionA = 'eng-race-session-a-' . Str::lower(Str::random(10));
        $sessionB = 'eng-race-session-b-' . Str::lower(Str::random(10));
        $anonA = 'eng-race-anon-a-' . Str::lower(Str::random(10));
        $anonB = 'eng-race-anon-b-' . Str::lower(Str::random(10));

        $payloads = [
            'a' => [
                'event_name' => 'custom.race',
                'anonymous_id' => $anonA,
                'email' => 'race-a@example.com',
                'session_identifier' => $sessionA,
                'session_started_at' => '2026-10-04T10:00:00Z',
                'occurred_at' => '2026-10-04T10:00:00Z',
                'path' => '/race-a',
                'idempotency_key' => $key,
                'utm_source' => 'race-a',
            ],
            'b' => [
                'event_name' => 'custom.race',
                'anonymous_id' => $anonB,
                'email' => 'race-b@example.com',
                'session_identifier' => $sessionB,
                'session_started_at' => '2026-10-04T10:00:00Z',
                'occurred_at' => '2026-10-04T10:05:00Z',
                'path' => '/race-b',
                'idempotency_key' => $key,
                'utm_source' => 'race-b',
            ],
        ];

        $results = EngineRace::run([
            'a' => static function (string $workdir) use ($propertyId, $payloads): array {
                engineEventRendezvous($workdir, 'a', 'b');

                $eventId = (string) app(IngestSignalEvent::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    $payloads['a'],
                    true
                )->id;

                return [
                    'event_id' => $eventId,
                    'barrier' => [
                        'reached' => is_file($workdir . '/event-ready-a'),
                        'peer_seen' => is_file($workdir . '/event-ready-b'),
                    ],
                ];
            },
            'b' => static function (string $workdir) use ($propertyId, $payloads): array {
                engineEventRendezvous($workdir, 'b', 'a');

                $eventId = (string) app(IngestSignalEvent::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    $payloads['b'],
                    true
                )->id;

                return [
                    'event_id' => $eventId,
                    'barrier' => [
                        'reached' => is_file($workdir . '/event-ready-b'),
                        'peer_seen' => is_file($workdir . '/event-ready-a'),
                    ],
                ];
            },
        ]);

        expect($results['a']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['b']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['a']['event_id'])->toBe($results['b']['event_id']);

        $events = SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();

        expect($events)->toHaveCount(1);

        $winner = $events->sole();

        $sessions = SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();
        $identities = SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->get();

        // The loser's whole transaction rolled back: only the winner's
        // session and identity side effects survive.
        expect($sessions)->toHaveCount(1)
            ->and($identities)->toHaveCount(1);

        $session = $sessions->sole();
        $identity = $identities->sole();

        $expectedTag = $winner->path === '/race-a' ? 'a' : 'b';
        $expectedSession = $expectedTag === 'a' ? $sessionA : $sessionB;
        $expectedAnon = $expectedTag === 'a' ? $anonA : $anonB;
        $expectedEmail = 'race-' . $expectedTag . '@example.com';
        $loserSession = $expectedTag === 'a' ? $sessionB : $sessionA;
        $loserAnon = $expectedTag === 'a' ? $anonB : $anonA;

        expect($winner->id)->toBe($results['a']['event_id'])
            ->and($winner->idempotency_key)->toBe($key)
            ->and($winner->ingestion_source)->toBe(SignalEvent::INGESTION_SOURCE_TRUSTED)
            ->and((string) $winner->signal_session_id)->toBe((string) $session->id)
            ->and((string) $winner->signal_identity_id)->toBe((string) $identity->id)
            ->and($session->session_identifier)->toBe($expectedSession)
            ->and($session->exit_path)->toBe($winner->path)
            ->and($session->ended_at?->toAtomString())->toBe($winner->occurred_at->toAtomString())
            ->and($identity->anonymous_id)->toBe($expectedAnon)
            ->and($identity->email)->toBe($expectedEmail)
            ->and(SignalSession::query()->withoutOwnerScope()
                ->where('tracked_property_id', $propertyId)
                ->where('session_identifier', $loserSession)->count())->toBe(0)
            ->and(SignalIdentity::query()->withoutOwnerScope()
                ->where('tracked_property_id', $propertyId)
                ->where('anonymous_id', $loserAnon)->count())->toBe(0);
    }
})->with(['pgsql', 'mysql']);

it('persists both events when the same key races across browser and trusted sources', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineIngestionRepeats(); $round++) {
        $property = $this->engineProperty('Engine Source Race');
        $propertyId = (string) $property->id;
        $key = 'eng-source-' . Str::lower(Str::random(10));
        $sessionIdentifier = 'eng-source-session-' . Str::lower(Str::random(10));
        $anonymousId = 'eng-source-anon-' . Str::lower(Str::random(10));

        $browserPayload = [
            'event_name' => 'custom.source',
            'anonymous_id' => $anonymousId,
            'session_identifier' => $sessionIdentifier,
            'session_started_at' => '2026-10-04T10:00:00Z',
            'occurred_at' => '2026-10-04T10:00:00Z',
            'path' => '/browser',
            'idempotency_key' => $key,
        ];
        $trustedPayload = [
            'event_name' => 'custom.source',
            'anonymous_id' => $anonymousId,
            'session_identifier' => $sessionIdentifier,
            'session_started_at' => '2026-10-04T10:00:00Z',
            'occurred_at' => '2026-10-04T10:00:00Z',
            'path' => '/trusted',
            'idempotency_key' => $key,
            'source_event_id' => 'eng-source-' . Str::lower(Str::random(10)),
        ];

        $results = EngineRace::run([
            'browser' => static fn (): array => ['event_id' => (string) app(IngestSignalEvent::class)->handle(
                TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                $browserPayload,
                false
            )->id],
            'trusted' => static fn (): array => ['event_id' => (string) app(IngestSignalEvent::class)->handle(
                TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                $trustedPayload,
                true
            )->id],
        ]);

        expect($results['browser']['event_id'])->not->toBe($results['trusted']['event_id']);

        $events = SignalEvent::query()->withoutOwnerScope()
            ->where('tracked_property_id', $propertyId)
            ->where('idempotency_key', $key)
            ->orderBy('ingestion_source')
            ->get();

        expect($events)->toHaveCount(2)
            ->and($events->pluck('ingestion_source')->all())->toEqualCanonicalizing([
                SignalEvent::INGESTION_SOURCE_BROWSER,
                SignalEvent::INGESTION_SOURCE_TRUSTED,
            ])
            ->and(SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $propertyId)->count())->toBe(1);
    }
})->with(['pgsql', 'mysql']);
