<?php

declare(strict_types=1);

namespace AIArmada\Signals\Jobs;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Services\SignalAlertDispatcher;
use AIArmada\Signals\Services\SignalAlertEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class EvaluateSignalAlertsForEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $signalEventId) {}

    public function handle(SignalAlertEvaluator $evaluator, SignalAlertDispatcher $dispatcher): void
    {
        $event = SignalEvent::query()
            ->withoutOwnerScope()
            ->find($this->signalEventId);

        if (! $event instanceof SignalEvent) {
            return;
        }

        $owner = OwnerContext::fromTypeAndId($event->owner_type, $event->owner_id);

        OwnerContext::withOwner($owner, function () use ($event, $evaluator, $dispatcher): void {
            SignalAlertRule::query()
                ->where('is_active', true)
                ->where(function ($query) use ($event): void {
                    $query->whereNull('tracked_property_id')
                        ->orWhere('tracked_property_id', $event->tracked_property_id);
                })
                ->orderByDesc('priority')
                ->each(function (SignalAlertRule $rule) use ($evaluator, $dispatcher): void {
                    if ($rule->isInCooldown()) {
                        return;
                    }

                    $result = $evaluator->evaluate($rule);

                    if (! $result['matched']) {
                        return;
                    }

                    $dispatcher->dispatch($rule, $result['metric_value'], $result['context']);
                });
        });
    }
}
