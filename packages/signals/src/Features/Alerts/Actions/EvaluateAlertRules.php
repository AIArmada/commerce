<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Alerts\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use AIArmada\Signals\Features\Alerts\Queries\AlertEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class EvaluateAlertRules
{
    public function __construct(
        private AlertEvaluator $evaluator,
        private SignalAlertDispatcher $dispatcher,
    ) {}

    /**
     * @return array{processed: int, skipped: int, dispatched: int}
     */
    public function handle(?string $trackedPropertyId = null, bool $dryRun = false, ?string $ruleId = null): array
    {
        return $this->processRules(
            SignalAlertRule::query()
                ->forOwner()
                ->when(filled($ruleId), fn (Builder $query): Builder => $query->whereKey($ruleId))
                ->where('is_active', true)
                ->when(
                    filled($trackedPropertyId),
                    function (Builder $query) use ($trackedPropertyId): Builder {
                        return $query->where(function (Builder $q) use ($trackedPropertyId): void {
                            $q->whereNull('tracked_property_id')
                                ->orWhere('tracked_property_id', $trackedPropertyId);
                        });
                    },
                )
                ->orderByDesc('priority'),
            $dryRun,
        );
    }

    /** @return array{processed: int, skipped: int, dispatched: int} */
    public function forOwners(?string $ruleId, bool $dryRun): array
    {
        $runner = new OwnerBatchRunner(SignalAlertRule::class, ['enabled' => 'signals.owner.enabled']);

        return $runner->run(fn (): array => $this->handle(dryRun: $dryRun, ruleId: $ruleId))
            ?? ['processed' => 0, 'skipped' => 0, 'dispatched' => 0];
    }

    /**
     * @return array{processed: int, skipped: int, dispatched: int}
     */
    private function processRules(Builder $query, bool $dryRun): array
    {
        $processed = 0;
        $skipped = 0;
        $dispatched = 0;

        $query->each(function (Model $model) use ($dryRun, &$processed, &$skipped, &$dispatched): void {
            $rule = $model instanceof SignalAlertRule ? $model : null;

            if ($rule === null) {
                return;
            }

            if ($rule->isInCooldown()) {
                $skipped++;

                return;
            }

            $result = $this->evaluator->evaluate($rule);
            $processed++;

            if (! $result['matched']) {
                return;
            }

            if (! $dryRun) {
                if ($this->dispatcher->dispatch($rule, $result['metric_value'], $result['context']) !== null) {
                    $dispatched++;
                } else {
                    $skipped++;
                }
            }
        });

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'dispatched' => $dispatched,
        ];
    }
}
