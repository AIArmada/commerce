<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\HasSubscriptionMatchContext;
use AIArmada\Engagement\Contracts\Subscribable;
use AIArmada\Engagement\Features\Subscriptions\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final readonly class MatchSubscriptionsAction
{
    public function __construct(private ManageSubscriptionsAction $subscriptions) {}

    public function execute(string $subjectType, string $subjectId, string $trigger): int
    {
        $modelClass = Relation::getMorphedModel($subjectType) ?? $subjectType;

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true) || ! is_a($modelClass, Subscribable::class, true)) {
            throw new InvalidArgumentException("Subject type [{$subjectType}] must resolve to a subscribable model.");
        }

        $found = false;
        $runner = new OwnerBatchRunner(
            Subscription::class,
            [
                'enabled' => 'engagement.owner.enabled',
                'include_global' => 'engagement.owner.include_global',
            ],
        );

        $processed = OwnerContext::withOwner(null, function () use ($runner, $modelClass, $subjectId, $trigger, &$found): int {
            return (int) $runner->forEach(function () use ($modelClass, $subjectId, $trigger, &$found): int {
                /** @var Model|null $model */
                $model = $modelClass::query()->find($subjectId);

                if (! $model instanceof Model) {
                    return 0;
                }

                $found = true;
                $context = $this->buildMatchContext($model);
                $matches = 0;

                foreach ($this->subscriptions->matchingSubscriptions($model, $trigger, $context) as $_subscription) {
                    $matches++;
                }

                return $matches;
            })->sum();
        });

        if (! $found) {
            throw new InvalidArgumentException("Subject not found for type [{$subjectType}] with ID [{$subjectId}].");
        }

        return $processed;
    }

    /**
     * Match context is a whitelist: subject identity plus whatever the
     * subject explicitly opts in via HasSubscriptionMatchContext. Raw model
     * attributes are never exposed to criteria matching.
     *
     * @return array<string, mixed>
     */
    private function buildMatchContext(Model $model): array
    {
        $context = [
            'subject_type' => $model->getMorphClass(),
            'subject_id' => (string) $model->getKey(),
        ];

        if ($model instanceof HasSubscriptionMatchContext) {
            return array_merge($model->subscriptionMatchContext(), $context);
        }

        return $context;
    }
}
