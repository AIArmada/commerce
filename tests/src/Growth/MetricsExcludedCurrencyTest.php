<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function excludedCurrencyOwner(): User
{
    return User::query()->create([
        'name' => 'Excluded Currency Owner ' . Str::random(6),
        'email' => 'excluded-currency-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function excludedCurrencyExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Excluded Currency Property ' . Str::random(6),
            'slug' => 'excluded-currency-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => 'active',
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        return $experiment->fresh(['variants', 'trackedProperty']) ?? $experiment;
    });
}

function excludedCurrencyLegacyEvent(User $owner, Experiment $experiment, array $properties, string $eventName, int $revenueMinor, string $currency): void
{
    $now = CarbonImmutable::now();

    DB::table((new SignalEvent)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'tracked_property_id' => $experiment->tracked_property_id,
        'signal_session_id' => null,
        'signal_identity_id' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'occurred_at' => $now,
        'event_name' => $eventName,
        'event_category' => 'conversion',
        'ingestion_source' => SignalEvent::INGESTION_SOURCE_TRUSTED,
        'revenue_minor' => $revenueMinor,
        'currency' => $currency,
        'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

describe('Metrics excluded-currency visibility', function (): void {
    it('reports other-currency revenue separately instead of dropping it', function (): void {
        $owner = excludedCurrencyOwner();
        $experiment = excludedCurrencyExperiment($owner);
        $variant = $experiment->variants->firstWhere('code', 'A');

        $assignment = OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'identity:currency-mix',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]));

        $properties = OwnerContext::withOwner($owner, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment));

        OwnerContext::withOwner($owner, fn (): SignalEvent => SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 2000,
            'currency' => 'MYR',
            'properties' => $properties,
        ]));

        excludedCurrencyLegacyEvent($owner, $experiment, $properties, 'order.paid', 1000, 'USD');
        excludedCurrencyLegacyEvent($owner, $experiment, $properties, 'order.refunded', 400, 'USD');

        $metrics = OwnerContext::withOwner($owner, fn (): array => app(AggregateExperimentMetrics::class)->handle($experiment));

        expect($metrics['currency'])->toBe('MYR')
            ->and($metrics['totals']['revenue_minor'])->toBe(2000)
            ->and($metrics['totals']['excluded_revenue_minor'])->toBe(600)
            ->and($metrics['totals']['excluded_events'])->toBe(2)
            ->and($metrics['totals']['purchases'])->toBe(2)
            ->and($metrics['totals']['refunds'])->toBe(1);
    });
});
