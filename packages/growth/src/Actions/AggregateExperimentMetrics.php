<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class AggregateExperimentMetrics
{
    /**
     * @return array{
     *     experiment_id: string,
    *     currency: string,
     *     winner_metric: string,
     *     winner_variant_id: string|null,
     *     totals: array{assignments: int, checkout_starts: int, purchases: int, refunds: int, revenue_minor: int},
     *     variants: array<int, array<string, float|int|string|null>>
     * }
     */
    public function handle(Experiment $experiment): array
    {
        $experiment->loadMissing('trackedProperty');
        $checkoutStartedEventName = (string) config('growth.integrations.signals.checkout_started_event_name', 'checkout.started');
        $purchaseEventName = (string) ($experiment->goal_event_name ?: config('growth.integrations.signals.purchase_event_name', 'order.paid'));
        $refundEventName = (string) config('growth.integrations.signals.refund_event_name', 'order.refunded');

        $variants = Variant::query()
            ->withoutOwnerScope()
            ->where('experiment_id', $experiment->getKey())
            ->get()
            ->sortBy('position')
            ->values();

        $assignments = Assignment::query()
            ->withoutOwnerScope()
            ->where('experiment_id', $experiment->getKey())
            ->get()
            ->groupBy('variant_id');

        $events = SignalEvent::query()
            ->withoutOwnerScope()
            ->where('tracked_property_id', $experiment->tracked_property_id)
            ->whereIn('event_name', [$checkoutStartedEventName, $purchaseEventName, $refundEventName])
            ->orderBy('occurred_at')
            ->get(['id', 'tracked_property_id', 'occurred_at', 'event_name', 'event_category', 'revenue_minor', 'currency', 'properties'])
            ->map(function (SignalEvent $event) use ($experiment): ?array {
                $context = $this->resolveContextForExperiment($event, $experiment);

                if ($context === null) {
                    return null;
                }

                return [
                    'event' => $event,
                    'context' => $context,
                ];
            })
            ->filter()
            ->values()
            ->groupBy(fn (array $payload): string => (string) Arr::get($payload, 'context.variant_id', ''));

        $winnerMetric = (string) $experiment->winner_metric;
        $variantMetrics = $variants->map(fn (Variant $variant): array => $this->variantMetrics($variant, $assignments, $events, $experiment))->all();

        $totals = [
            'assignments' => array_sum(array_map(static fn (array $metrics): int => (int) $metrics['assignments'], $variantMetrics)),
            'checkout_starts' => array_sum(array_map(static fn (array $metrics): int => (int) $metrics['checkout_starts'], $variantMetrics)),
            'purchases' => array_sum(array_map(static fn (array $metrics): int => (int) $metrics['purchases'], $variantMetrics)),
            'refunds' => array_sum(array_map(static fn (array $metrics): int => (int) $metrics['refunds'], $variantMetrics)),
            'revenue_minor' => array_sum(array_map(static fn (array $metrics): int => (int) $metrics['revenue_minor'], $variantMetrics)),
        ];

        $winnerVariantId = collect($variantMetrics)
            ->sortByDesc(fn (array $metrics): array => [
                (float) ($metrics[$winnerMetric] ?? 0),
                (int) $metrics['assignments'],
                -1 * (int) $metrics['position'],
            ])
            ->first()['variant_id'] ?? null;

        return [
            'experiment_id' => (string) $experiment->getKey(),
            'currency' => (string) ($experiment->trackedProperty?->currency ?? config('signals.defaults.currency', 'MYR')),
            'winner_metric' => $winnerMetric,
            'winner_variant_id' => is_string($winnerVariantId) ? $winnerVariantId : null,
            'totals' => $totals,
            'variants' => $variantMetrics,
        ];
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function variantMetrics(
        Variant $variant,
        Collection $assignments,
        Collection $events,
        Experiment $experiment,
    ): array {
        /** @var Collection<int, Assignment> $variantAssignments */
        $variantAssignments = $assignments->get((string) $variant->getKey(), collect());
        /** @var Collection<int, array{event: SignalEvent, context: array<string, string>}> $variantEvents */
        $variantEvents = $events->get((string) $variant->getKey(), collect());

        $checkoutStartedEventName = (string) config('growth.integrations.signals.checkout_started_event_name', 'checkout.started');
        $purchaseEventName = (string) ($experiment->goal_event_name ?: config('growth.integrations.signals.purchase_event_name', 'order.paid'));
        $refundEventName = (string) config('growth.integrations.signals.refund_event_name', 'order.refunded');

        $checkoutStarts = $variantEvents
            ->filter(fn (array $payload): bool => $payload['event']->event_name === $checkoutStartedEventName)
            ->count();
        $purchases = $variantEvents
            ->filter(fn (array $payload): bool => $payload['event']->event_name === $purchaseEventName)
            ->count();
        $refunds = $variantEvents
            ->filter(fn (array $payload): bool => $payload['event']->event_name === $refundEventName)
            ->count();
        $purchaseRevenue = (int) $variantEvents
            ->filter(fn (array $payload): bool => $payload['event']->event_name === $purchaseEventName)
            ->sum(fn (array $payload): int => (int) $payload['event']->revenue_minor);
        $refundRevenue = (int) $variantEvents
            ->filter(fn (array $payload): bool => $payload['event']->event_name === $refundEventName)
            ->sum(fn (array $payload): int => (int) $payload['event']->revenue_minor);
        $revenueMinor = $purchaseRevenue - $refundRevenue;
        $assignmentCount = $variantAssignments->count();
        $conversionRate = $assignmentCount > 0 ? round($purchases / $assignmentCount, 4) : 0.0;
        $revenuePerVisitor = $assignmentCount > 0 ? round($revenueMinor / $assignmentCount, 2) : 0.0;

        return [
            'variant_id' => (string) $variant->getKey(),
            'code' => (string) $variant->code,
            'name' => (string) $variant->name,
            'position' => (int) $variant->position,
            'assignments' => $assignmentCount,
            'checkout_starts' => $checkoutStarts,
            'purchases' => $purchases,
            'refunds' => $refunds,
            'revenue_minor' => $revenueMinor,
            'conversion_rate' => $conversionRate,
            'revenue_per_visitor' => $revenuePerVisitor,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveContextForExperiment(SignalEvent $event, Experiment $experiment): ?array
    {
        $properties = is_array($event->properties) ? $event->properties : [];
        $contexts = data_get($properties, 'experiment_contexts');

        if (is_array($contexts)) {
            foreach ($contexts as $context) {
                $normalized = $this->normalizeContext($context);

                if ($normalized === null) {
                    continue;
                }

                if ($normalized['experiment_id'] === (string) $experiment->getKey()) {
                    return $normalized;
                }
            }
        }

        $singleContext = $this->normalizeContext($properties);

        if ($singleContext === null || $singleContext['experiment_id'] !== (string) $experiment->getKey()) {
            return null;
        }

        return $singleContext;
    }

    /**
     * @return array<string, string>|null
     */
    private function normalizeContext(mixed $context): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        $experimentId = data_get($context, 'experiment_id');
        $variantId = data_get($context, 'variant_id');

        if (! is_scalar($experimentId) || ! is_scalar($variantId)) {
            return null;
        }

        return [
            'experiment_id' => (string) $experimentId,
            'variant_id' => (string) $variantId,
        ];
    }
}