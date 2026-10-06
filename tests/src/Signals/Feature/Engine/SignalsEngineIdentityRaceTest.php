<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Commerce\Tests\Signals\Support\EngineRace;
use AIArmada\Signals\Actions\IdentifySignalIdentity;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(SignalsEngineTestCase::class);

function engineIdentityRepeats(): int
{
    return 5;
}

/**
 * Child-local insert rendezvous: both workers must reach the identity
 * creating hook for the shared external id after both misses before either
 * proceeds to INSERT, forcing a genuine unique collision inside caller-owned
 * outer transactions instead of a start-barrier-only overlap claim.
 * Sentinel identities bypass the barrier.
 */
function engineIdentityRendezvous(string $workdir, string $role, string $peer, string $sharedExternalId): void
{
    SignalIdentity::creating(static function (SignalIdentity $model) use ($workdir, $role, $peer, $sharedExternalId): bool {
        if ($model->external_id !== $sharedExternalId) {
            return true;
        }

        file_put_contents($workdir . '/ident-ready-' . $role, (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($workdir . '/ident-ready-' . $peer)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('identity insert rendezvous timed out waiting for [' . $peer . '].');
            }

            usleep(1000);
        }

        return true;
    });
}

it('recovers duplicate identity creation under genuine concurrency', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineIdentityRepeats(); $round++) {
        $property = $this->engineProperty('Engine Identity Race');
        $propertyId = (string) $property->id;
        $externalId = 'eng-ident-' . Str::lower(Str::random(10));
        $sentinelA = 'eng-ident-sentinel-a-' . Str::lower(Str::random(10));
        $sentinelB = 'eng-ident-sentinel-b-' . Str::lower(Str::random(10));

        $payload = [
            'external_id' => $externalId,
            'email' => 'race-identity@example.com',
        ];

        $results = EngineRace::run([
            'a' => static function (string $workdir) use ($propertyId, $payload, $externalId, $sentinelA): array {
                engineIdentityRendezvous($workdir, 'a', 'b', $externalId);

                $resolved = DB::transaction(function () use ($propertyId, $payload, $sentinelA): array {
                    $winnerId = (string) app(IdentifySignalIdentity::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $payload
                    )->id;

                    $sentinelId = (string) app(IdentifySignalIdentity::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        ['external_id' => $sentinelA]
                    )->id;

                    return ['winner_id' => $winnerId, 'sentinel_id' => $sentinelId];
                }, 5);

                return [
                    'identity_id' => $resolved['winner_id'],
                    'sentinel_id' => $resolved['sentinel_id'],
                    'barrier' => [
                        'reached' => is_file($workdir . '/ident-ready-a'),
                        'peer_seen' => is_file($workdir . '/ident-ready-b'),
                    ],
                ];
            },
            'b' => static function (string $workdir) use ($propertyId, $payload, $externalId, $sentinelB): array {
                engineIdentityRendezvous($workdir, 'b', 'a', $externalId);

                $resolved = DB::transaction(function () use ($propertyId, $payload, $sentinelB): array {
                    $winnerId = (string) app(IdentifySignalIdentity::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        $payload
                    )->id;

                    $sentinelId = (string) app(IdentifySignalIdentity::class)->handle(
                        TrackedProperty::query()->withoutOwnerScope()->findOrFail($propertyId),
                        ['external_id' => $sentinelB]
                    )->id;

                    return ['winner_id' => $winnerId, 'sentinel_id' => $sentinelId];
                }, 5);

                return [
                    'identity_id' => $resolved['winner_id'],
                    'sentinel_id' => $resolved['sentinel_id'],
                    'barrier' => [
                        'reached' => is_file($workdir . '/ident-ready-b'),
                        'peer_seen' => is_file($workdir . '/ident-ready-a'),
                    ],
                ];
            },
        ]);

        expect($results['a']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['b']['barrier'])->toBe(['reached' => true, 'peer_seen' => true])
            ->and($results['a']['identity_id'])->toBe($results['b']['identity_id']);

        $identities = SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $propertyId)
            ->where('external_id', $externalId)
            ->get();

        expect($identities)->toHaveCount(1)
            ->and((string) $identities->sole()->id)->toBe($results['a']['identity_id']);

        $sentinelRowA = SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $propertyId)
            ->where('external_id', $sentinelA)
            ->first();
        $sentinelRowB = SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $propertyId)
            ->where('external_id', $sentinelB)
            ->first();

        expect($sentinelRowA)->not->toBeNull()
            ->and((string) $sentinelRowA->id)->toBe($results['a']['sentinel_id'])
            ->and($sentinelRowB)->not->toBeNull()
            ->and((string) $sentinelRowB->id)->toBe($results['b']['sentinel_id']);
    }
})->with(['pgsql', 'mysql']);

it('recovers duplicate identity creation inside an outer transaction after a concurrent commit', function (string $engine): void {
    $this->useEngine($engine);

    if ($engine === 'mysql') {
        $this->setEngineSessionIsolation('REPEATABLE READ');
    }

    $property = $this->engineProperty('Engine Identity Outer');
    $externalId = 'eng-ident-outer-' . Str::lower(Str::random(10));
    $secondExternalId = 'eng-ident-outer-second-' . Str::lower(Str::random(10));

    DB::beginTransaction();

    try {
        SignalIdentity::query()
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $externalId)
            ->first();

        DB::connection($this->engineSecondConnection)
            ->table((new SignalIdentity)->getTable())
            ->insert([
                'id' => (string) Str::uuid(),
                'tracked_property_id' => $property->id,
                'owner_type' => $property->owner_type,
                'owner_id' => $property->owner_id,
                'external_id' => $externalId,
                'email' => 'outer-first@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $identity = app(IdentifySignalIdentity::class)->handle($property, [
            'external_id' => $externalId,
            'email' => 'outer-second@example.com',
        ]);

        $winnerId = DB::connection($this->engineSecondConnection)
            ->table((new SignalIdentity)->getTable())
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $externalId)
            ->value('id');

        expect((string) $identity->id)->toBe((string) $winnerId)
            ->and((string) $identity->external_id)->toBe($externalId);

        // The outer transaction stays usable after the duplicate recovery.
        $second = app(IdentifySignalIdentity::class)->handle($property, [
            'external_id' => $secondExternalId,
        ]);

        expect((string) $second->id)->not->toBeEmpty();

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();

        throw $e;
    }

    expect(SignalIdentity::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->where('external_id', $externalId)
        ->count())->toBe(1)
        ->and(SignalIdentity::query()->withoutOwnerScope()
            ->where('tracked_property_id', $property->id)
            ->where('external_id', $secondExternalId)
            ->count())->toBe(1);
})->with(['pgsql', 'mysql']);
