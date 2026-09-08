<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\Subscribable;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class MatchSubscriptionsCommand extends Command
{
    protected $signature = 'engagement:match-subscriptions
                            {subjectType : The morph class type of the subject}
                            {subjectId : The ID of the subject}
                            {--trigger= : The trigger event name}';

    protected $description = 'Match subscriptions to a subject';

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $trigger = $this->option('trigger');

        if ($trigger === null) {
            $this->error('The --trigger option is required.');

            return self::FAILURE;
        }

        $subjectType = (string) $this->argument('subjectType');
        $subjectId = (string) $this->argument('subjectId');
        $modelClass = Relation::getMorphedModel($subjectType) ?? $subjectType;

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            $this->error("Subject type [{$subjectType}] does not resolve to a model class.");

            return self::FAILURE;
        }

        if (! is_a($modelClass, Subscribable::class, true)) {
            $this->error("Subject type [{$subjectType}] must implement " . Subscribable::class . '.');

            return self::FAILURE;
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

                foreach ($this->subscriptionManager->matchingSubscriptions($model, $trigger, $context) as $_subscription) {
                    $matches++;
                }

                return $matches;
            })->sum();
        });

        if (! $found) {
            $this->error("Subject not found for type [{$subjectType}] with ID [{$subjectId}].");

            return self::FAILURE;
        }

        $this->info("Processed {$processed} subscription matches for trigger: {$trigger}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMatchContext(Model $model): array
    {
        return array_merge($model->attributesToArray(), [
            'subject_type' => $model->getMorphClass(),
            'subject_id' => (string) $model->getKey(),
        ]);
    }
}
