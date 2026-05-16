<?php

declare(strict_types=1);

use AIArmada\Growth\Actions\ResolveExperimentPreset;

it('returns the expected defaults for supported preset modules', function (string $moduleType, string $goalEventName, string $winnerMetric, array $expectedSettingsKeys): void {
    $preset = app(ResolveExperimentPreset::class)->handle($moduleType);

    expect($preset['module_type'])->toBe($moduleType)
        ->and($preset['goal_event_name'])->toBe($goalEventName)
        ->and($preset['winner_metric'])->toBe($winnerMetric)
        ->and(array_keys($preset['settings']))->toEqualCanonicalizing($expectedSettingsKeys);
})->with([
    'ab test' => ['ab_test', 'order.paid', 'revenue_per_visitor', []],
    'sales page test' => ['sales_page_test', 'order.paid', 'revenue_per_visitor', ['cta_event_name', 'destination_urls', 'entry_paths']],
    'funnel test' => ['funnel_test', 'order.paid', 'revenue_per_visitor', ['funnel_steps']],
    'pricing test' => ['pricing_test', 'order.paid', 'revenue_per_visitor', ['checkout_event_name', 'price_labels']],
]);