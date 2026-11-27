<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\EntryPoints\Http\DefaultRequestExperimentSubjectResolver;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Features\Ingestion\Models\SignalIdentity;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

function identityFallbackOwner(): User
{
    return User::query()->create([
        'name' => 'Identity Fallback Owner ' . Str::random(6),
        'email' => 'identity-fallback-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

describe('Authenticated identity matching', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('ignores fallback identities stamped for another user type', function (): void {
        $owner = identityFallbackOwner();

        $experiment = OwnerContext::withOwner($owner, function (): Experiment {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Identity Fallback Property ' . Str::random(6),
                'slug' => 'identity-fallback-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            /** @var Experiment $experiment */
            $experiment = Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
                'status' => ExperimentStatus::Active,
            ]);

            return $experiment->fresh(['trackedProperty']) ?? $experiment;
        });

        // Same external identifier as the request user, but stamped for a
        // different user type: attributing it would merge assignments across
        // user types.
        OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'external_id' => (string) $owner->getKey(),
            'auth_user_type' => 'Alien\\Visitor',
            'auth_user_id' => 'someone-else',
        ]));

        $request = Request::create('/sales-page', 'GET');
        $request->setUserResolver(fn (): User => $owner);

        $subjects = OwnerContext::withOwner(
            $owner,
            fn () => app(DefaultRequestExperimentSubjectResolver::class)->resolve($request, $experiment)
        );

        expect($subjects->identity)->toBeNull();
    });

    it('does not attribute an unstamped external identifier to an authenticated user', function (): void {
        $owner = identityFallbackOwner();

        $experiment = OwnerContext::withOwner($owner, function (): Experiment {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Unstamped Identity Property ' . Str::random(6),
                'slug' => 'identity-unstamped-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            /** @var Experiment $experiment */
            $experiment = Experiment::factory()->create([
                'tracked_property_id' => $trackedProperty->getKey(),
                'status' => ExperimentStatus::Active,
            ]);

            return $experiment->fresh(['trackedProperty']) ?? $experiment;
        });

        OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'external_id' => (string) $owner->getKey(),
        ]));

        $request = Request::create('/sales-page', 'GET');
        $request->setUserResolver(fn (): User => $owner);

        $subjects = OwnerContext::withOwner(
            $owner,
            fn () => app(DefaultRequestExperimentSubjectResolver::class)->resolve($request, $experiment)
        );

        expect($subjects->identity)->toBeNull();
    });
    it('matches only the authenticated user type and identifier', function (): void {
        $owner = identityFallbackOwner();

        $experiment = OwnerContext::withOwner($owner, function (): Experiment {
            $property = TrackedProperty::query()->create([
                'name' => 'Authenticated Identity Property',
                'slug' => 'identity-auth-' . Str::lower(Str::random(8)),
                'write_key' => Str::random(40),
                'type' => 'website',
                'timezone' => 'UTC',
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            return Experiment::factory()->create([
                'tracked_property_id' => $property->getKey(),
                'status' => ExperimentStatus::Active,
            ]);
        });

        $identity = OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'external_id' => 'independent-external-id',
            'auth_user_type' => $owner->getMorphClass(),
            'auth_user_id' => (string) $owner->getKey(),
        ]));

        $request = Request::create('/sales-page', 'GET');
        $request->setUserResolver(fn (): User => $owner);

        $subjects = OwnerContext::withOwner(
            $owner,
            fn () => app(DefaultRequestExperimentSubjectResolver::class)->resolve($request, $experiment)
        );

        expect($subjects->identity?->getKey())->toBe($identity->getKey());
    });
});
