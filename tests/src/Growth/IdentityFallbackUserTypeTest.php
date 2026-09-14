<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Support\Http\DefaultRequestExperimentSubjectResolver;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
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

describe('Identity fallback user-type matching', function (): void {
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

    it('still matches unstamped fallback identities for the request user', function (): void {
        $owner = identityFallbackOwner();

        $experiment = OwnerContext::withOwner($owner, function (): Experiment {
            $trackedProperty = TrackedProperty::query()->create([
                'name' => 'Identity Fallback Legacy Property ' . Str::random(6),
                'slug' => 'identity-legacy-' . Str::lower(Str::random(8)),
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

        $legacy = OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'external_id' => (string) $owner->getKey(),
        ]));

        $request = Request::create('/sales-page', 'GET');
        $request->setUserResolver(fn (): User => $owner);

        $subjects = OwnerContext::withOwner(
            $owner,
            fn () => app(DefaultRequestExperimentSubjectResolver::class)->resolve($request, $experiment)
        );

        expect($subjects->identity)->not->toBeNull()
            ->and((string) $subjects->identity->getKey())->toBe((string) $legacy->getKey());
    });
});
