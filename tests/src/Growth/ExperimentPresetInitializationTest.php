<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Str;

function growthPresetInitializationOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Preset Owner ' . Str::random(6),
        'email' => 'growth-preset-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

it('applies module preset defaults when creating experiments with blank module-specific fields', function (): void {
    $owner = growthPresetInitializationOwner();

    $trackedProperty = OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Preset Property ' . Str::random(6),
        'slug' => 'preset-property-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'module_type' => 'funnel_test',
        'goal_event_name' => null,
        'goal_event_category' => null,
        'winner_metric' => null,
        'settings' => null,
    ]));

    expect($experiment->module_type)->toBe('funnel_test')
        ->and($experiment->goal_event_name)->toBe('order.paid')
        ->and($experiment->goal_event_category)->toBe('conversion')
        ->and($experiment->winner_metric)->toBe('revenue_per_visitor')
        ->and(data_get($experiment->settings, 'funnel_steps'))->toBeArray()
        ->and(data_get($experiment->settings, 'funnel_steps'))->toHaveCount(3);
});

it('preserves explicit custom module types while still filling generic defaults', function (): void {
    $owner = growthPresetInitializationOwner();

    $trackedProperty = OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Custom Module Property ' . Str::random(6),
        'slug' => 'custom-module-property-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'module_type' => 'whatsapp_followup_test',
        'goal_event_name' => null,
        'goal_event_category' => null,
        'winner_metric' => null,
        'settings' => null,
    ]));

    expect($experiment->module_type)->toBe('whatsapp_followup_test')
        ->and($experiment->goal_event_name)->toBe('order.paid')
        ->and($experiment->goal_event_category)->toBe('conversion')
        ->and($experiment->winner_metric)->toBe('revenue_per_visitor')
        ->and($experiment->settings)->toBeNull();
});

it('removes stale preset settings when switching between supported module types', function (): void {
    $owner = growthPresetInitializationOwner();

    $trackedProperty = OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Switching Preset Property ' . Str::random(6),
        'slug' => 'switching-preset-property-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'module_type' => 'funnel_test',
        'settings' => [
            'funnel_steps' => [
                [
                    'label' => 'Landing',
                    'event_name' => 'page_view',
                    'event_category' => 'page_view',
                ],
            ],
        ],
    ]));

    OwnerContext::withOwner($owner, function () use ($experiment): void {
        $experiment->module_type = 'pricing_test';
        $experiment->settings = [
            'funnel_steps' => [
                [
                    'label' => 'Should Disappear',
                    'event_name' => 'legacy',
                    'event_category' => 'legacy',
                ],
            ],
            'checkout_event_name' => 'checkout.custom',
            'price_labels' => ['Starter', 'Pro'],
        ];
        $experiment->save();
    });

    $experiment->refresh();

    expect($experiment->settings)->toBe([
        'checkout_event_name' => 'checkout.custom',
        'price_labels' => ['Starter', 'Pro'],
    ]);
});

it('clears stale settings when switching to a preset module without settings', function (): void {
    $owner = growthPresetInitializationOwner();

    $trackedProperty = OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Empty Preset Property ' . Str::random(6),
        'slug' => 'empty-preset-property-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    $experiment = OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'module_type' => 'sales_page_test',
        'settings' => [
            'entry_paths' => ['/offers/course-v1'],
            'destination_urls' => ['/checkout/course'],
            'cta_event_name' => 'cta_click',
        ],
    ]));

    OwnerContext::withOwner($owner, function () use ($experiment): void {
        $experiment->module_type = 'ab_test';
        $experiment->save();
    });

    $experiment->refresh();

    expect($experiment->settings)->toBeNull();
});
