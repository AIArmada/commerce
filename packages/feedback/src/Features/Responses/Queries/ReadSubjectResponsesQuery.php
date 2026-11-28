<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectResponses;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReadSubjectResponsesQuery implements ReadsSubjectResponses
{
    public function subjectIds(string $type, string $id): array
    {
        return FeedbackResponse::query()->where('subject_type', $type)->where('subject_id', $id)
            ->orderBy('id')->pluck('id')->all();
    }

    public function respondentIds(string $type, string $id): array
    {
        return FeedbackResponse::query()->where('respondent_type', $type)->where('respondent_id', $id)
            ->orderBy('id')->pluck('id')->all();
    }

    public function hasSubmitted(string $respondentType, string $respondentId, string $subjectType, string $subjectId, ?string $formId = null): bool
    {
        return $this->matching($respondentType, $respondentId, $subjectType, $subjectId, $formId)
            ->whereIn('status', [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed])->exists();
    }

    public function latestId(string $respondentType, string $respondentId, string $subjectType, string $subjectId, ?string $formId = null): ?string
    {
        return $this->matching($respondentType, $respondentId, $subjectType, $subjectId, $formId)
            ->latest()->orderByDesc('id')->first()?->id;
    }

    /** @return Builder<FeedbackResponse> */
    private function matching(string $respondentType, string $respondentId, string $subjectType, string $subjectId, ?string $formId): Builder
    {
        $query = FeedbackResponse::query()->where('respondent_type', $respondentType)
            ->where('respondent_id', $respondentId)
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId);

        if ($formId !== null) {
            $query->where('feedback_form_id', $formId);
        }

        return $query;
    }
}
