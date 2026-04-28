<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Support\SavedSignalReportMutationGuard;
use AIArmada\FilamentSignals\Support\SignalsReportStateSanitizer;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Validation\ValidationException;

uses(FilamentSignalsTestCase::class);

it('rejects inaccessible tracked property and segment ids during saved report mutations', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Signals Guard Owner A',
        'email' => 'signals-guard-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Signals Guard Owner B',
        'email' => 'signals-guard-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));
    TrackedProperty::query()->create([
        'name' => 'Owner A Property',
        'slug' => 'owner-a-property',
        'write_key' => 'owner-a-property-key',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));
    $ownerBProperty = TrackedProperty::query()->create([
        'name' => 'Owner B Property',
        'slug' => 'owner-b-property',
        'write_key' => 'owner-b-property-key',
    ]);
    $ownerBSegment = SignalSegment::query()->create([
        'name' => 'Owner B Segment',
        'slug' => 'owner-b-segment',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(fn () => app(SavedSignalReportMutationGuard::class)->sanitize([
        'name' => 'Invalid scoped report',
        'slug' => 'invalid-scoped-report',
        'report_type' => 'acquisition',
        'tracked_property_id' => $ownerBProperty->id,
        'signal_segment_id' => $ownerBSegment->id,
    ]))->toThrow(ValidationException::class, 'Selected tracked property is not accessible.');
});

it('rejects inaccessible goal slugs during funnel mutation writes', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Signals Goal Guard Owner A',
        'email' => 'signals-goal-guard-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Signals Goal Guard Owner B',
        'email' => 'signals-goal-guard-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));
    $ownerAProperty = TrackedProperty::query()->create([
        'name' => 'Owner A Goal Property',
        'slug' => 'owner-a-goal-property',
        'write_key' => 'owner-a-goal-property-key',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));
    SignalGoal::query()->create([
        'name' => 'Owner B Goal',
        'slug' => 'owner-b-goal',
        'event_name' => 'conversion.completed',
        'goal_type' => 'conversion',
        'tracked_property_id' => null,
        'is_active' => true,
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(fn () => app(SavedSignalReportMutationGuard::class)->sanitize([
        'name' => 'Guarded Funnel',
        'slug' => 'guarded-funnel',
        'report_type' => 'conversion_funnel',
        'tracked_property_id' => $ownerAProperty->id,
        'settings' => [
            'funnel_steps' => [
                [
                    'label' => 'Complete',
                    'step_type' => 'goal',
                    'goal_slug' => 'owner-b-goal',
                ],
            ],
        ],
    ]))->toThrow(ValidationException::class, 'Selected goal is not accessible.');
});

it('rejects inaccessible tracked property ids for goal and alert rule mutations', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Signals Property Guard Owner A',
        'email' => 'signals-property-guard-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Signals Property Guard Owner B',
        'email' => 'signals-property-guard-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));
    $ownerBProperty = TrackedProperty::query()->create([
        'name' => 'Owner B Guarded Property',
        'slug' => 'owner-b-guarded-property',
        'write_key' => 'owner-b-guarded-property-key',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(fn () => app(TrackedPropertyMutationGuard::class)->sanitize([
        'tracked_property_id' => $ownerBProperty->id,
    ]))->toThrow(ValidationException::class, 'Selected tracked property is not accessible.');
});

it('silently clears invalid report page state ids', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Signals State Owner A',
        'email' => 'signals-state-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Signals State Owner B',
        'email' => 'signals-state-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));
    $ownerAProperty = TrackedProperty::query()->create([
        'name' => 'Owner A State Property',
        'slug' => 'owner-a-state-property',
        'write_key' => 'owner-a-state-property-key',
    ]);
    $ownerASegment = SignalSegment::query()->create([
        'name' => 'Owner A Segment',
        'slug' => 'owner-a-state-segment',
    ]);
    $ownerAReport = SavedSignalReport::query()->create([
        'name' => 'Owner A Acquisition Report',
        'slug' => 'owner-a-acquisition-report',
        'report_type' => 'acquisition',
        'is_active' => true,
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));
    $ownerBProperty = TrackedProperty::query()->create([
        'name' => 'Owner B State Property',
        'slug' => 'owner-b-state-property',
        'write_key' => 'owner-b-state-property-key',
    ]);
    $ownerBReport = SavedSignalReport::query()->create([
        'name' => 'Owner B Acquisition Report',
        'slug' => 'owner-b-acquisition-report',
        'report_type' => 'acquisition',
        'is_active' => true,
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $sanitizer = app(SignalsReportStateSanitizer::class);

    expect($sanitizer->sanitizeTrackedPropertyId($ownerAProperty->id))->toBe($ownerAProperty->id)
        ->and($sanitizer->sanitizeSignalSegmentId($ownerASegment->id))->toBe($ownerASegment->id)
        ->and($sanitizer->sanitizeSavedReportId($ownerAReport->id, 'acquisition'))->toBe($ownerAReport->id)
        ->and($sanitizer->sanitizeTrackedPropertyId($ownerBProperty->id))->toBe('')
        ->and($sanitizer->sanitizeSavedReportId($ownerBReport->id, 'acquisition'))->toBe('')
        ->and($sanitizer->sanitizeSavedReportId($ownerAReport->id, 'retention'))->toBe('');
});
