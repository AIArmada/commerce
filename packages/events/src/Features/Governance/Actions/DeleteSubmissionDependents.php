<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Features\Governance\Models\EventSubmissionAttachment;
use AIArmada\Events\Features\Governance\Models\EventSubmissionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DeleteSubmissionDependents
{
    public function handle(EventSubmission $submission): void
    {
        DB::transaction(function () use ($submission): void {
            $this->deleteThroughModels(EventSubmissionLog::class, (string) $submission->getKey());
            $this->deleteThroughModels(EventSubmissionAttachment::class, (string) $submission->getKey());
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deleteThroughModels(string $modelClass, string $submissionId): void
    {
        $modelClass::query()
            ->where('event_submission_id', $submissionId)
            ->chunkById(200, function (iterable $children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
    }
}
