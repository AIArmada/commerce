<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Counters\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Engagement\Features\Counters\Models\EngagementCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final readonly class ReconcileEngagementCountersAction
{
    public function __construct(private UpdateEngagementCountersAction $counterService) {}

    public function execute(?string $subjectType, ?string $subjectId, ?string $type): void
    {
        if (($subjectType === null) !== ($subjectId === null)) {
            throw new InvalidArgumentException('Provide both subjectType and subjectId, or neither.');
        }

        if ($type !== null && ! in_array($type, ['bookmarks', 'responses', 'followers', 'reactions'], true)) {
            throw new InvalidArgumentException('Unsupported counter type.');
        }

        if ($subjectType !== null && $subjectId !== null) {
            $this->reconcileSingle($subjectType, $subjectId, $type);

            return;
        }

        $this->reconcileAll($type);
    }

    private function reconcileSingle(string $subjectType, string $subjectId, ?string $type): void
    {
        $modelClass = Relation::getMorphedModel($subjectType) ?? $subjectType;

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException("Subject type [{$subjectType}] does not resolve to a model class.");
        }

        $runner = $this->batchRunner();

        $reconciled = false;

        $runner->forEachAcrossOwners(function () use ($modelClass, $subjectId, $type, &$reconciled): void {
            $subject = (new $modelClass)->newQuery()->find($subjectId);

            if (! $subject) {
                return;
            }

            $reconciled = true;
            $this->reconcileSubject($subject, $type);
        });

        if (! $reconciled) {
            throw new InvalidArgumentException("Subject not found for type [{$subjectType}] with ID [{$subjectId}].");
        }

    }

    private function reconcileAll(?string $type): void
    {
        $runner = $this->batchRunner();

        $runner->forEachAcrossOwners(function () use ($type): void {
            EngagementCounter::query()
                ->select('subject_type', 'subject_id')
                ->distinct()
                ->orderBy('subject_type')
                ->orderBy('subject_id')
                ->cursor()
                ->each(function (EngagementCounter $row) use ($type): void {
                    $modelClass = Relation::getMorphedModel($row->subject_type) ?? $row->subject_type;

                    if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
                        return;
                    }

                    $subject = (new $modelClass)->newQuery()->find($row->subject_id);

                    if (! $subject) {
                        return;
                    }

                    $this->reconcileSubject($subject, $type);
                });
        });
    }

    private function batchRunner(): OwnerBatchRunner
    {
        return new OwnerBatchRunner(
            EngagementCounter::class,
            [
                'enabled' => 'engagement.owner.enabled',
                'include_global' => 'engagement.owner.include_global',
            ],
        );
    }

    private function reconcileSubject(mixed $subject, ?string $type): void
    {
        if ($type !== null) {
            match ($type) {
                'bookmarks' => $this->counterService->recalculateBookmarks($subject),
                'responses' => $this->counterService->recalculateResponses($subject),
                'followers' => $this->counterService->recalculateFollowers($subject),
                'reactions' => $this->counterService->recalculateReactions($subject),
                default => null,
            };

            return;
        }

        $this->counterService->recalculate($subject);
    }
}
