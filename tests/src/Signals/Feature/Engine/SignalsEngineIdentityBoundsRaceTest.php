<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Signals\Actions\IdentifySignalIdentity;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

uses(SignalsEngineTestCase::class);

function engineIdentityBoundsRounds(): int
{
    return 3;
}

/**
 * Deterministic duplicate staging: while a losing create attempt is inside
 * its creating hook, the hook raw-inserts the already committed winner
 * through the second connection, so both the unique violation and the
 * recovery onto the winner are forced instead of left to scheduling.
 *
 * Sentinel identities bypass the staging.
 *
 * @param  array{id: string, first_seen_at: string, last_seen_at: string}  $winner
 */
function engineIdentityBoundsStageWinner(
    string $externalId,
    string $connection,
    array $winner,
    string $propertyId,
    ?string $ownerType,
    ?string $ownerId,
): void {
    $staged = false;

    SignalIdentity::creating(static function (SignalIdentity $model) use (
        $externalId,
        $connection,
        $winner,
        $propertyId,
        $ownerType,
        $ownerId,
        &$staged
    ): bool {
        if ($model->external_id !== $externalId || $staged) {
            return true;
        }

        $staged = true;

        DB::connection($connection)->table($model->getTable())->insert([
            'id' => $winner['id'],
            'tracked_property_id' => $propertyId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'external_id' => $externalId,
            'email' => 'staged-winner@example.com',
            'first_seen_at' => $winner['first_seen_at'],
            'last_seen_at' => $winner['last_seen_at'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    });
}

it('keeps identity bounds chronological under concurrent observations at READ COMMITTED', function (): void {
    $this->useEngine();

    $level = 'READ COMMITTED';

    for ($round = 0; $round < engineIdentityBoundsRounds(); $round++) {
        $property = $this->engineProperty('Engine Identity Bounds RC');
        $propertyId = (string) $property->id;
        $externalId = 'eng-bounds-' . Str::lower(Str::random(10));

        app(IdentifySignalIdentity::class)->handle($property, [
            'external_id' => $externalId,
            'seen_at' => '2026-10-10T12:00:00Z',
        ]);

        $results = EngineRace::run([
            'newer' => static function (string $workdir) use ($propertyId, $externalId, $level): array {
                DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $level);

                $identity = app(IdentifySignalIdentity::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    ['external_id' => $externalId, 'seen_at' => '2026-10-17T12:00:00Z'],
                );

                return [
                    'identity_id' => (string) $identity->id,
                    'first_seen_at' => $identity->first_seen_at?->toAtomString(),
                    'last_seen_at' => $identity->last_seen_at?->toAtomString(),
                ];
            },
            'older' => static function (string $workdir) use ($propertyId, $externalId, $level): array {
                DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $level);

                $identity = app(IdentifySignalIdentity::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    ['external_id' => $externalId, 'seen_at' => '2026-10-01T12:00:00Z'],
                );

                return [
                    'identity_id' => (string) $identity->id,
                    'first_seen_at' => $identity->first_seen_at?->toAtomString(),
                    'last_seen_at' => $identity->last_seen_at?->toAtomString(),
                ];
            },
        ]);

        $identity = SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $propertyId)
            ->where('external_id', $externalId)
            ->firstOrFail();

        expect($results['newer']['identity_id'])->toBe($results['older']['identity_id'])
            ->and($identity->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
            ->and($identity->last_seen_at?->toAtomString())->toBe('2026-10-17T12:00:00+00:00');
    }
});

it('keeps identity bounds chronological under concurrent observations at REPEATABLE READ', function (): void {
    $this->useEngine();

    $level = 'REPEATABLE READ';

    for ($round = 0; $round < engineIdentityBoundsRounds(); $round++) {
        $property = $this->engineProperty('Engine Identity Bounds RR');
        $propertyId = (string) $property->id;
        $externalId = 'eng-bounds-rr-' . Str::lower(Str::random(10));

        app(IdentifySignalIdentity::class)->handle($property, [
            'external_id' => $externalId,
            'seen_at' => '2026-10-10T12:00:00Z',
        ]);

        $results = EngineRace::run([
            'newer' => static function (string $workdir) use ($propertyId, $externalId, $level): array {
                DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $level);

                $identity = app(IdentifySignalIdentity::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    ['external_id' => $externalId, 'seen_at' => '2026-10-17T12:00:00Z'],
                );

                return [
                    'identity_id' => (string) $identity->id,
                    'first_seen_at' => $identity->first_seen_at?->toAtomString(),
                    'last_seen_at' => $identity->last_seen_at?->toAtomString(),
                ];
            },
            'older' => static function (string $workdir) use ($propertyId, $externalId, $level): array {
                DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $level);

                $identity = app(IdentifySignalIdentity::class)->handle(
                    TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                    ['external_id' => $externalId, 'seen_at' => '2026-10-01T12:00:00Z'],
                );

                return [
                    'identity_id' => (string) $identity->id,
                    'first_seen_at' => $identity->first_seen_at?->toAtomString(),
                    'last_seen_at' => $identity->last_seen_at?->toAtomString(),
                ];
            },
        ]);

        $identity = SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $propertyId)
            ->where('external_id', $externalId)
            ->firstOrFail();

        expect($results['newer']['identity_id'])->toBe($results['older']['identity_id'])
            ->and($identity->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
            ->and($identity->last_seen_at?->toAtomString())->toBe('2026-10-17T12:00:00+00:00');
    }
});

it('merges a concurrent commit into a caller transaction through the identity row lock', function (): void {
    $this->useEngine();
    $this->setEngineSessionIsolation('READ COMMITTED');

    $property = $this->engineProperty('Engine Identity Caller');
    $externalId = 'eng-bounds-caller-' . Str::lower(Str::random(10));

    app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => $externalId,
        'seen_at' => '2026-10-10T12:00:00Z',
    ]);

    DB::beginTransaction();

    try {
        // The caller transaction reads the identity before the concurrent
        // commit below, so a merge that does not re-read under the row lock
        // observes stale bounds.
        SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $externalId)
            ->firstOrFail();

        DB::connection($this->engineSecondConnection)
            ->table((new SignalIdentity)->getTable())
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $externalId)
            ->update(['first_seen_at' => '2026-10-01T12:00:00Z']);

        $identity = app(IdentifySignalIdentity::class)->handle($property, [
            'external_id' => $externalId,
            'seen_at' => '2026-10-05T12:00:00Z',
        ]);

        expect($identity->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
            ->and($identity->last_seen_at?->toAtomString())->toBe('2026-10-10T12:00:00+00:00');

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();

        throw $e;
    }

    $identity = SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', $externalId)
        ->firstOrFail();

    expect($identity->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
        ->and($identity->last_seen_at?->toAtomString())->toBe('2026-10-10T12:00:00+00:00');
});

it('merges identity bounds when duplicate creation recovers onto a committed winner at READ COMMITTED', function (): void {
    $this->useEngine();
    $this->setEngineSessionIsolation('READ COMMITTED');

    $property = $this->engineProperty('Engine Identity Duplicate RC');
    $externalId = 'eng-bounds-dup-' . Str::lower(Str::random(10));
    $winnerId = (string) Str::uuid();

    engineIdentityBoundsStageWinner(
        externalId: $externalId,
        connection: $this->engineSecondConnection,
        winner: [
            'id' => $winnerId,
            'first_seen_at' => '2026-10-17T12:00:00Z',
            'last_seen_at' => '2026-10-17T12:00:00Z',
        ],
        propertyId: (string) $property->id,
        ownerType: $property->owner_type,
        ownerId: $property->owner_id,
    );

    $identity = app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => $externalId,
        'seen_at' => '2026-10-01T12:00:00Z',
    ]);

    $persisted = SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', $externalId)
        ->firstOrFail();

    expect((string) $identity->id)->toBe($winnerId)
        ->and((string) $persisted->id)->toBe($winnerId)
        ->and($persisted->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
        ->and($persisted->last_seen_at?->toAtomString())->toBe('2026-10-17T12:00:00+00:00')
        ->and(SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $externalId)
            ->count())->toBe(1);
});

it('merges identity bounds when duplicate creation recovers onto a committed winner at REPEATABLE READ', function (): void {
    $this->useEngine();
    $this->setEngineSessionIsolation('REPEATABLE READ');

    $property = $this->engineProperty('Engine Identity Duplicate RR');
    $externalId = 'eng-bounds-dup-rr-' . Str::lower(Str::random(10));
    $winnerId = (string) Str::uuid();

    engineIdentityBoundsStageWinner(
        externalId: $externalId,
        connection: $this->engineSecondConnection,
        winner: [
            'id' => $winnerId,
            'first_seen_at' => '2026-10-17T12:00:00Z',
            'last_seen_at' => '2026-10-17T12:00:00Z',
        ],
        propertyId: (string) $property->id,
        ownerType: $property->owner_type,
        ownerId: $property->owner_id,
    );

    $identity = app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => $externalId,
        'seen_at' => '2026-10-01T12:00:00Z',
    ]);

    $persisted = SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', $externalId)
        ->firstOrFail();

    expect((string) $identity->id)->toBe($winnerId)
        ->and((string) $persisted->id)->toBe($winnerId)
        ->and($persisted->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
        ->and($persisted->last_seen_at?->toAtomString())->toBe('2026-10-17T12:00:00+00:00');
});

it('reaches chronological identity bounds after a caller retry under REPEATABLE READ', function (): void {
    $this->useEngine();
    $this->setEngineSessionIsolation('REPEATABLE READ');

    $property = $this->engineProperty('Engine Identity Caller RR');
    $externalId = 'eng-bounds-retry-' . Str::lower(Str::random(10));

    app(IdentifySignalIdentity::class)->handle($property, [
        'external_id' => $externalId,
        'seen_at' => '2026-10-10T12:00:00Z',
    ]);

    $attempts = 0;
    $secondConnection = $this->engineSecondConnection;

    DB::transaction(function () use ($property, $externalId, $secondConnection, &$attempts): void {
        $attempts++;

        // Establish this attempt's snapshot before the concurrent commit.
        SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $externalId)
            ->firstOrFail();

        if ($attempts === 1) {
            DB::connection($secondConnection)
                ->table((new SignalIdentity)->getTable())
                ->where('tracked_property_id', $property->id)
                ->where('external_id', $externalId)
                ->update(['first_seen_at' => '2026-10-01T12:00:00Z']);
        }

        app(IdentifySignalIdentity::class)->handle($property, [
            'external_id' => $externalId,
            'seen_at' => '2026-10-05T12:00:00Z',
        ]);
    }, 5);

    $identity = SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', $externalId)
        ->firstOrFail();

    expect($attempts)->toBe(2)
        ->and($identity->first_seen_at?->toAtomString())->toBe('2026-10-01T12:00:00+00:00')
        ->and($identity->last_seen_at?->toAtomString())->toBe('2026-10-10T12:00:00+00:00');
});
