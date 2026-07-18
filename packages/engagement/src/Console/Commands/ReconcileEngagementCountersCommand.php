<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Console\Commands;

use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Models\EngagementCounter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ReconcileEngagementCountersCommand extends Command
{
    protected $signature = 'engagement:reconcile-counters
                            {subjectType? : The morph class type of the subject}
                            {subjectId? : The ID of the subject}
                            {--type= : Only reconcile specific counter types (bookmarks,responses,followers,reactions)}';

    protected $description = 'Reconcile engagement counters from source records';

    public function __construct(
        private readonly EngagementCounterService $counterService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $subjectType = $this->argument('subjectType');
        $subjectId = $this->argument('subjectId');
        $type = $this->option('type');

        if ($subjectType !== null && $subjectId !== null) {
            return $this->reconcileSingle((string) $subjectType, (string) $subjectId, $type);
        }

        return $this->reconcileAll($type);
    }

    private function reconcileSingle(string $subjectType, string $subjectId, ?string $type): int
    {
        $modelClass = Relation::getMorphedModel($subjectType) ?? $subjectType;

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            $this->error("Subject type [{$subjectType}] does not resolve to a model class.");

            return self::FAILURE;
        }

        $subject = (new $modelClass)->newQuery()->find($subjectId);

        if (! $subject) {
            $this->error("Subject not found for type [{$subjectType}] with ID [{$subjectId}].");

            return self::FAILURE;
        }

        $this->reconcileSubject($subject, $type);
        $this->info("Reconciled counters for [{$subjectType}:{$subjectId}].");

        return self::SUCCESS;
    }

    private function reconcileAll(?string $type): int
    {
        EngagementCounter::query()
            ->select('subject_type', 'subject_id')
            ->distinct()
            ->chunk(100, function (Collection $rows) use ($type): void {
                foreach ($rows as $row) {
                    $modelClass = Relation::getMorphedModel($row->subject_type) ?? $row->subject_type;

                    if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
                        continue;
                    }

                    $subject = (new $modelClass)->newQuery()->find($row->subject_id);

                    if (! $subject) {
                        continue;
                    }

                    $this->reconcileSubject($subject, $type);
                }
            });

        $this->info('Reconciled all engagement counters.');

        return self::SUCCESS;
    }

    private function reconcileSubject(mixed $subject, ?string $type): void
    {
        if ($type !== null) {
            match ($type) {
                'bookmarks' => $this->counterService->recalculateBookmarks($subject),
                'responses' => $this->counterService->recalculateResponses($subject),
                'followers' => $this->counterService->recalculate($subject),
                'reactions' => $this->counterService->recalculate($subject),
                default => null,
            };

            return;
        }

        $this->counterService->recalculate($subject);
    }
}
