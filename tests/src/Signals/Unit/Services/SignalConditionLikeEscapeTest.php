<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalCondition;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

function seedLikeEscapeEvents(): array
{
    $property = TrackedProperty::query()->create([
        'name' => 'Like Escape Property',
        'slug' => 'like-escape-property',
        'write_key' => 'like-escape-key',
    ]);

    $percent = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page.view',
        'path' => '/sale-100%-off',
        'source' => 'promo_a',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:06:00'),
        'event_name' => 'page.view',
        'path' => '/sale-1000-off',
        'source' => 'promoXa',
    ]);

    return [$property, $percent];
}

it('matches escaped percent signs in direct-field contains searches', function (): void {
    [$property, $percent] = seedLikeEscapeEvents();

    $matches = SignalCondition::applyToQuery(
        SignalEvent::query()->where('tracked_property_id', $property->id),
        [['field' => 'path', 'operator' => 'contains', 'value' => '100%']],
    )->get();

    expect($matches->pluck('id')->all())->toBe([$percent->id]);
});

it('matches escaped underscores literally in direct-field searches', function (): void {
    [$property] = seedLikeEscapeEvents();

    $matches = SignalCondition::applyToQuery(
        SignalEvent::query()->where('tracked_property_id', $property->id),
        [['field' => 'source', 'operator' => 'contains', 'value' => 'promo_a']],
    )->get();

    expect($matches)->toHaveCount(1)
        ->and($matches->first()?->source)->toBe('promo_a');
});

it('matches escaped wildcards with prefix and suffix operators', function (): void {
    [$property, $percent] = seedLikeEscapeEvents();

    $prefixed = SignalCondition::applyToQuery(
        SignalEvent::query()->where('tracked_property_id', $property->id),
        [['field' => 'path', 'operator' => 'starts_with', 'value' => '/sale-100%']],
    )->get();

    $suffixed = SignalCondition::applyToQuery(
        SignalEvent::query()->where('tracked_property_id', $property->id),
        [['field' => 'path', 'operator' => 'ends_with', 'value' => '100%-off']],
    )->get();

    expect($prefixed->pluck('id')->all())->toBe([$percent->id])
        ->and($suffixed->pluck('id')->all())->toBe([$percent->id]);
});
