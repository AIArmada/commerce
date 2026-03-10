<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SavedSignalReportDefinition;

uses(SignalsTestCase::class);

it('scopes segments and saved reports to the current owner and nulls deleted references', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Segment Owner A',
        'email' => 'segment-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Segment Owner B',
        'email' => 'segment-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Property',
        'slug' => 'owner-a-property',
        'write_key' => 'owner-a-report-key',
    ]);

    $segmentA = SignalSegment::query()->create([
        'name' => 'Purchasers',
        'slug' => 'purchasers',
        'match_type' => 'all',
        'conditions' => [
            ['field' => 'event_name', 'operator' => 'equals', 'value' => 'order.paid'],
        ],
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Property',
        'slug' => 'owner-b-property',
        'write_key' => 'owner-b-report-key',
    ]);

    $segmentB = SignalSegment::query()->create([
        'name' => 'Browsers',
        'slug' => 'browsers',
        'match_type' => 'any',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $reportA = SavedSignalReport::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_segment_id' => $segmentA->id,
        'name' => 'Owner A Funnel',
        'slug' => 'owner-a-funnel',
        'report_type' => 'conversion_funnel',
        'filters' => [
            ['key' => 'date_from', 'value' => '2026-03-01'],
        ],
        'settings' => [
            ['key' => 'comparison', 'value' => 'previous_period'],
        ],
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $reportB = SavedSignalReport::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_segment_id' => $segmentB->id,
        'name' => 'Owner B Live',
        'slug' => 'owner-b-live',
        'report_type' => 'live_activity',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(SignalSegment::query()->forOwner()->pluck('id')->all())
        ->toBe([$segmentA->id])
        ->and(SavedSignalReport::query()->forOwner()->pluck('id')->all())
        ->toBe([$reportA->id]);

    $propertyA->delete();
    $segmentA->delete();

    expect($reportA->fresh())
        ->not()->toBeNull()
        ->and($reportA->fresh()?->tracked_property_id)->toBeNull()
        ->and($reportA->fresh()?->signal_segment_id)->toBeNull()
        ->and($reportA->fresh()?->normalizedFilters())->toBe([
            'date_from' => '2026-03-01',
        ]);
});

it('blocks owner reassignment after a signal record has been created', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Immutable Owner A',
        'email' => 'immutable-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Immutable Owner B',
        'email' => 'immutable-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $property = TrackedProperty::query()->create([
        'name' => 'Immutable Property',
        'slug' => 'immutable-property',
        'write_key' => 'immutable-property-key',
    ]);

    $property->assignOwner($ownerB);

    expect(fn (): bool => $property->save())
        ->toThrow(InvalidArgumentException::class, 'Cross-tenant write blocked: owner columns cannot be reassigned after creation.');
});

it('blocks saved reports from referencing another owners property or segment', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Saved Report Guard Owner A',
        'email' => 'saved-report-guard-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Saved Report Guard Owner B',
        'email' => 'saved-report-guard-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Guarded Property',
        'slug' => 'owner-a-guarded-property',
        'write_key' => 'owner-a-guarded-property-key',
    ]);

    $segmentA = SignalSegment::query()->create([
        'name' => 'Owner A Guarded Segment',
        'slug' => 'owner-a-guarded-segment',
        'match_type' => 'all',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Guarded Property',
        'slug' => 'owner-b-guarded-property',
        'write_key' => 'owner-b-guarded-property-key',
    ]);

    $segmentB = SignalSegment::query()->create([
        'name' => 'Owner B Guarded Segment',
        'slug' => 'owner-b-guarded-segment',
        'match_type' => 'all',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    expect(fn (): SavedSignalReport => SavedSignalReport::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_segment_id' => $segmentA->id,
        'name' => 'Cross Tenant Property Reference',
        'slug' => 'cross-tenant-property-reference',
        'report_type' => 'page_views',
    ]))->toThrow(RuntimeException::class, 'Invalid tracked_property_id: does not belong to the current owner scope.');

    expect(fn (): SavedSignalReport => SavedSignalReport::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_segment_id' => $segmentB->id,
        'name' => 'Cross Tenant Segment Reference',
        'slug' => 'cross-tenant-segment-reference',
        'report_type' => 'page_views',
    ]))->toThrow(RuntimeException::class, 'Invalid signal_segment_id: does not belong to the current owner scope.');

    expect(fn (): SavedSignalReport => SavedSignalReport::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_segment_id' => $segmentA->id,
        'name' => 'Invalid Saved Report Filters',
        'slug' => 'invalid-saved-report-filters',
        'report_type' => 'page_views',
        'filters' => [
            ['key' => 'event_name', 'value' => 'page_view'],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid saved report filter at index 0: unsupported key [event_name].');

    expect(fn (): SavedSignalReport => SavedSignalReport::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_segment_id' => $segmentA->id,
        'name' => 'Invalid Saved Report Date Range',
        'slug' => 'invalid-saved-report-date-range',
        'report_type' => 'page_views',
        'filters' => [
            ['key' => 'date_from', 'value' => '2026-03-10'],
            ['key' => 'date_to', 'value' => '2026-03-01'],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid saved report filters: date_from cannot be after date_to.');
});

it('rejects invalid segment definitions when saving', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Segment Validation Owner',
        'email' => 'segment-validation-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    expect(fn (): SignalSegment => SignalSegment::query()->create([
        'name' => 'Bad Match Type Segment',
        'slug' => 'bad-match-type-segment',
        'match_type' => 'none',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid signal segment match type [none].');

    expect(fn (): SignalSegment => SignalSegment::query()->create([
        'name' => 'Bad Field Segment',
        'slug' => 'bad-field-segment',
        'match_type' => 'all',
        'conditions' => [
            ['field' => 'properties.bad key', 'operator' => 'equals', 'value' => 'chip'],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid signal segment condition at index 0: unsupported field [properties.bad key].');

    expect(fn (): SignalSegment => SignalSegment::query()->create([
        'name' => 'Bad Numeric Segment',
        'slug' => 'bad-numeric-segment',
        'match_type' => 'all',
        'conditions' => [
            ['field' => 'path', 'operator' => 'greater_than', 'value' => '10'],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid signal segment condition at index 0: operator [greater_than] requires a numeric field.');

    expect(fn (): SignalSegment => SignalSegment::query()->create([
        'name' => 'Numeric Property Segment',
        'slug' => 'numeric-property-segment',
        'match_type' => 'all',
        'conditions' => [
            ['field' => 'properties.checkout.attempt_count', 'operator' => 'greater_than_or_equal', 'value' => '2'],
            ['field' => 'properties.checkout.gateway', 'operator' => 'not_equals', 'value' => 'stripe'],
        ],
    ]))->not()->toThrow(InvalidArgumentException::class);
});

it('normalizes structured saved report settings and rejects invalid funnel settings', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Saved Settings Owner',
        'email' => 'saved-settings-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Saved Settings Property',
        'slug' => 'saved-settings-property',
        'write_key' => 'saved-settings-property-key',
    ]);

    $conversionFunnel = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Structured Funnel Settings',
        'slug' => 'structured-funnel-settings',
        'report_type' => 'conversion_funnel',
        'settings' => [
            'funnel_steps' => [
                [
                    'label' => 'Landing',
                    'event_name' => 'landing_viewed',
                    'event_category' => 'page_view',
                ],
                [
                    'label' => 'Signup',
                    'event_name' => 'signup_started',
                    'event_category' => 'engagement',
                ],
            ],
            'step_window_minutes' => '45',
        ],
    ]);

    $acquisition = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Structured Acquisition Settings',
        'slug' => 'structured-acquisition-settings',
        'report_type' => 'acquisition',
        'settings' => [
            'attribution_model' => SavedSignalReportDefinition::ATTRIBUTION_MODEL_LAST_TOUCH,
            'conversion_event_name' => 'subscription_activated',
        ],
    ]);

    expect($conversionFunnel->fresh()?->normalizedSettings())
        ->toBe([
            'funnel_steps' => [
                [
                    'label' => 'Landing',
                    'event_name' => 'landing_viewed',
                    'event_category' => 'page_view',
                ],
                [
                    'label' => 'Signup',
                    'event_name' => 'signup_started',
                    'event_category' => 'engagement',
                ],
            ],
            'step_window_minutes' => 45,
        ])
        ->and($acquisition->fresh()?->normalizedSettings())
        ->toBe([
            'attribution_model' => SavedSignalReportDefinition::ATTRIBUTION_MODEL_LAST_TOUCH,
            'conversion_event_name' => 'subscription_activated',
        ]);

    expect(fn (): SavedSignalReport => SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Invalid Funnel Settings',
        'slug' => 'invalid-funnel-settings',
        'report_type' => 'conversion_funnel',
        'settings' => [
            'funnel_steps' => [
                [
                    'label' => 'Only One',
                    'event_name' => 'landing_viewed',
                ],
            ],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid saved report settings: funnel reports require at least two valid steps.');
});
