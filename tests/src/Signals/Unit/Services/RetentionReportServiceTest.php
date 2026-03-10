<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\RetentionReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds an owner-safe retention cohort report', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Retention Owner A',
        'email' => 'retention-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Retention Owner B',
        'email' => 'retention-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Retention Property',
        'slug' => 'owner-a-retention-property',
        'write_key' => 'owner-a-retention-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Retention Property',
        'slug' => 'owner-b-retention-property',
        'write_key' => 'owner-b-retention-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $identityA1 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'retention-a-1',
        'first_seen_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-02-10 10:00:00'),
    ]);
    $identityA1->assignOwner($ownerA)->save();

    $identityA2 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'retention-a-2',
        'first_seen_at' => CarbonImmutable::parse('2026-01-01 12:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-01-05 12:00:00'),
    ]);
    $identityA2->assignOwner($ownerA)->save();

    $identityA3 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'retention-a-3',
        'first_seen_at' => CarbonImmutable::parse('2026-01-10 09:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-02-20 09:00:00'),
    ]);
    $identityA3->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'retention-b-1',
        'first_seen_at' => CarbonImmutable::parse('2026-01-01 08:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-03-01 08:00:00'),
    ]);
    $identityB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(RetentionReportService::class);
    $summary = $service->summary($propertyA->id, '2026-01-01', '2026-01-31');
    $rows = $service->rows($propertyA->id, '2026-01-01', '2026-01-31');

    expect($summary['cohorts'])->toBe(2)
        ->and($summary['identities'])->toBe(3)
        ->and($summary['windows'])->toBe([
            [
                'days' => 7,
                'retained' => 2,
                'avg_retention_rate' => 75.0,
            ],
            [
                'days' => 30,
                'retained' => 2,
                'avg_retention_rate' => 75.0,
            ],
        ])
        ->and($rows)->toHaveCount(2)
        ->and($rows[0]['cohort_date'])->toBe('2026-01-10')
        ->and($rows[0]['cohort_size'])->toBe(1)
        ->and($rows[0]['windows'])->toBe([
            [
                'days' => 7,
                'retained' => 1,
                'retention_rate' => 100.0,
            ],
            [
                'days' => 30,
                'retained' => 1,
                'retention_rate' => 100.0,
            ],
        ])
        ->and($rows[1]['cohort_date'])->toBe('2026-01-01')
        ->and($rows[1]['cohort_size'])->toBe(2)
        ->and($rows[1]['windows'])->toBe([
            [
                'days' => 7,
                'retained' => 1,
                'retention_rate' => 50.0,
            ],
            [
                'days' => 30,
                'retained' => 1,
                'retention_rate' => 50.0,
            ],
        ])
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Retention Property']);
});

it('uses saved retention windows from saved reports', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Retention Windows Owner',
        'email' => 'retention-windows-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Retention Windows Property',
        'slug' => 'retention-windows-property',
        'write_key' => 'retention-windows-key',
    ]);

    SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'retention-window-identity-1',
        'first_seen_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-01-20 10:00:00'),
    ]);

    SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'retention-window-identity-2',
        'first_seen_at' => CarbonImmutable::parse('2026-01-01 12:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-01-02 12:00:00'),
    ]);

    $savedReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Retention 1d and 14d',
        'slug' => 'retention-1d-14d',
        'report_type' => 'retention',
        'settings' => [
            'retention_windows' => [
                ['days' => 1],
                ['days' => 14],
            ],
        ],
    ]);

    $service = app(RetentionReportService::class);
    $summary = $service->summary(null, '2026-01-01', '2026-01-31', null, $savedReport->id);
    $rows = $service->rows(null, '2026-01-01', '2026-01-31', null, $savedReport->id);

    expect($summary['windows'])->toBe([
        [
            'days' => 1,
            'retained' => 2,
            'avg_retention_rate' => 100.0,
        ],
        [
            'days' => 14,
            'retained' => 1,
            'avg_retention_rate' => 50.0,
        ],
    ])
        ->and($rows[0]['windows'])->toBe([
            [
                'days' => 1,
                'retained' => 2,
                'retention_rate' => 100.0,
            ],
            [
                'days' => 14,
                'retained' => 1,
                'retention_rate' => 50.0,
            ],
        ])
        ->and($service->getSavedReportOptions())
        ->toBe([$savedReport->id => 'Retention 1d and 14d']);
});
