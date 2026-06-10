<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Enums\VariantStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

function growthPresentationCreateOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Presentation Owner ' . Str::random(6),
        'email' => 'growth-presentation-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthPresentationCreateExperiment(User $owner, ExperimentStatus $status = ExperimentStatus::Active): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($status): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Growth Presentation Property ' . Str::random(6),
            'slug' => 'growth-presentation-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => $status,
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'hero-a',
            'name' => 'Hero A',
            'traffic_percentage' => 50,
            'position' => 1,
            'is_control' => true,
            'status' => VariantStatus::Active,
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'hero-b',
            'name' => 'Hero B',
            'traffic_percentage' => 50,
            'position' => 2,
            'is_control' => false,
            'status' => VariantStatus::Active,
        ]);

        return $experiment->fresh(['variants', 'trackedProperty']) ?? $experiment;
    });
}

function growthPresentationCreateIdentityForUser(TrackedProperty $trackedProperty, User $owner): SignalIdentity
{
    return OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'external_id' => (string) $owner->getKey(),
        'anonymous_id' => 'identity-anon-' . Str::lower(Str::random(8)),
        'email' => $owner->email,
        'auth_user_type' => $owner->getMorphClass(),
        'auth_user_id' => (string) $owner->getKey(),
    ]));
}

function growthPresentationCreateSessionForIdentifier(
    TrackedProperty $trackedProperty,
    User $owner,
    string $sessionIdentifier,
    ?SignalIdentity $identity = null,
): SignalSession {
    return OwnerContext::withOwner($owner, fn (): SignalSession => SignalSession::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'signal_identity_id' => $identity?->getKey(),
        'session_identifier' => $sessionIdentifier,
        'started_at' => now(),
    ]));
}

function growthPresentationBindRequest(Request $request, ?User $owner = null): void
{
    app()->instance('request', $request);
    OwnerContext::setForRequest($owner);
}

function growthPresentationAttachStartedSession(Request $request): string
{
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);

    return (string) $session->getId();
}
