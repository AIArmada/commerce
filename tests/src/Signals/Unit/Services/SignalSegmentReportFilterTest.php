<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('applies owner-safe segment filters to event, session, and identity queries', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Segment Filter Owner',
        'email' => 'segment-filter-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Segment Filter Property',
        'slug' => 'segment-filter-property',
        'write_key' => 'segment-filter-key',
    ]);

    $matchingIdentity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'matching-identity',
        'first_seen_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-03-20 09:00:00'),
    ]);

    $otherIdentity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'other-identity',
        'first_seen_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-03-12 10:00:00'),
    ]);

    $matchingSession = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $matchingIdentity->id,
        'session_identifier' => 'matching-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'entry_path' => '/pricing',
        'exit_path' => '/checkout',
    ]);

    $otherSession = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $otherIdentity->id,
        'session_identifier' => 'other-session',
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
        'entry_path' => '/blog',
        'exit_path' => '/contact',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_session_id' => $matchingSession->id,
        'signal_identity_id' => $matchingIdentity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_session_id' => $otherSession->id,
        'signal_identity_id' => $otherIdentity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/blog',
    ]);

    $segment = SignalSegment::query()->create([
        'name' => 'Pricing Visitors',
        'slug' => 'pricing-visitors',
        'conditions' => [
            ['field' => 'path', 'operator' => 'equals', 'value' => '/pricing'],
        ],
    ]);

    $filter = app(SignalSegmentReportFilter::class);

    expect($filter->applyToEventQuery(SignalEvent::query(), $segment->id)->count())->toBe(1)
        ->and($filter->applyToSessionQuery(SignalSession::query(), $segment->id)->pluck('id')->all())->toBe([$matchingSession->id])
        ->and($filter->applyToIdentityQuery(SignalIdentity::query(), $segment->id)->pluck('id')->all())->toBe([$matchingIdentity->id])
        ->and($filter->applyToEventQuery(SignalEvent::query(), 'missing-segment')->count())->toBe(0)
        ->and($filter->getSegmentOptions())->toBe([$segment->id => 'Pricing Visitors']);
});
