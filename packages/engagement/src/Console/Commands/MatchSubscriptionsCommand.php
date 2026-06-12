<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

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

        $query = $modelClass::query();

        if (in_array(HasOwner::class, class_uses_recursive($modelClass), true)) {
            $query = $query->withoutGlobalScope(OwnerScope::class);
        }

        /** @var Model|null $model */
        $model = $query->find($subjectId);

        if (! $model instanceof Model) {
            $this->error("Subject not found for type [{$subjectType}] with ID [{$subjectId}].");

            return self::FAILURE;
        }

        $model->loadMissing('owner');

        /** @var Model|null $owner */
        $owner = $model->getRelation('owner');
        $context = $this->buildMatchContext($model);

        OwnerContext::withOwner($owner, function () use ($model, $trigger, $context): void {
            foreach ($this->subscriptionManager->matchingSubscriptions($model, $trigger, $context) as $_subscription) {
            }
        });

        $this->info("Processed subscription matches for trigger: {$trigger}");

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
