<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\Cart\Models\AlertRule;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Data\AlertEvent;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use AIArmada\FilamentCart\Services\AlertEvaluator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class ProcessAlertsCommand extends Command
{
    protected $signature = 'cart:process-alerts
                            {--rule= : Process a specific rule by ID}
                            {--event-type= : Process alerts for a specific event type}
                            {--dry-run : Show what would be processed without dispatching}';

    protected $description = 'Evaluate and dispatch cart alerts based on configured rules';

    public function __construct(
        private readonly AlertEvaluator $evaluator,
        private readonly AlertDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ruleId = $this->option('rule');
        $eventType = $this->option('event-type');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No alerts will be dispatched');
            $this->newLine();
        }

        $summary = $this->processForOwners($ruleId, $eventType, (bool) $dryRun);

        $this->newLine();
        $this->info("Summary: {$summary['processed']} processed, {$summary['skipped']} skipped (cooldown), {$summary['dispatched']} dispatched");

        return self::SUCCESS;
    }

    /**
     * @return array{processed: int, skipped: int, dispatched: int}
     */
    private function processForOwners(?string $ruleId, ?string $eventType, bool $dryRun): array
    {
        if (! AlertRule::ownerScopingEnabled()) {
            return $this->processScoped($ruleId, $eventType, $dryRun);
        }

        if (OwnerContext::resolve() !== null) {
            return $this->processScoped($ruleId, $eventType, $dryRun);
        }

        $owners = AlertRule::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return $this->processScoped($ruleId, $eventType, $dryRun);
        }

        $totals = [
            'processed' => 0,
            'skipped' => 0,
            'dispatched' => 0,
        ];

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);

            $result = OwnerContext::withOwner(
                $owner,
                fn (): array => $this->processScoped($ruleId, $eventType, $dryRun)
            );

            $totals['processed'] += $result['processed'];
            $totals['skipped'] += $result['skipped'];
            $totals['dispatched'] += $result['dispatched'];
        }

        return $totals;
    }

    /**
     * @return array{processed: int, skipped: int, dispatched: int}
     */
    private function processScoped(?string $ruleId, ?string $eventType, bool $dryRun): array
    {
        $query = AlertRule::query()->forOwner()->where('is_active', true);

        if ($ruleId) {
            $query->where('id', $ruleId);
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $rules = $query->orderBy('priority', 'desc')->get();

        if ($rules->isEmpty()) {
            $this->info('No active alert rules found.');

            return [
                'processed' => 0,
                'skipped' => 0,
                'dispatched' => 0,
            ];
        }

        $this->info("Processing {$rules->count()} alert rule(s)...");
        $this->newLine();

        $processed = 0;
        $skipped = 0;
        $dispatched = 0;

        foreach ($rules as $rule) {
            $this->line("Rule: <info>{$rule->name}</info> ({$rule->event_type})");

            if ($rule->isInCooldown()) {
                $remaining = $rule->getCooldownRemainingMinutes();
                $this->line("  ⏸ In cooldown ({$remaining} minutes remaining)");
                $skipped++;

                continue;
            }

            $sampleEventData = $this->getSampleEventData($rule->event_type);

            if ($this->evaluator->evaluate($rule, $sampleEventData)) {
                $this->line('  ✓ Conditions matched');

                if (! $dryRun) {
                    $event = AlertEvent::custom(
                        $rule->event_type,
                        $rule->severity,
                        $rule->name,
                        $rule->description ?? "Alert triggered by rule: {$rule->name}",
                        $sampleEventData,
                    );

                    $log = $this->dispatcher->dispatch($rule, $event);
                    $this->line('  → Dispatched to: ' . implode(', ', $log->channels_notified));
                    $dispatched++;
                } else {
                    $this->line('  → Would dispatch to: ' . implode(', ', $rule->getEnabledChannels()));
                }
            } else {
                $this->line('  ✗ Conditions not matched');
            }

            $processed++;
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'dispatched' => $dispatched,
        ];
    }

    private function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }

    /**
     * Get sample event data for testing rules.
     *
     * @return array<string, mixed>
     */
    private function getSampleEventData(string $eventType): array
    {
        return match ($eventType) {
            'abandonment' => [
                'cart_value_cents' => 15000,
                'items_count' => 3,
                'time_since_abandonment_minutes' => 45,
                'customer_type' => 'returning',
            ],
            'high_value' => [
                'cart_value_cents' => 25000,
                'items_count' => 5,
                'customer_tier' => 'vip',
            ],
            'recovery' => [
                'cart_value_cents' => 8000,
                'abandonment_age_hours' => 2,
                'recovery_probability' => 0.65,
            ],
            default => [
                'event_type' => $eventType,
                'timestamp' => now()->toIso8601String(),
            ],
        };
    }
}
