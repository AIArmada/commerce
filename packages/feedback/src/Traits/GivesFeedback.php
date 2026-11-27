<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Traits;

use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectResponses;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait GivesFeedback
{
    /** @return list<string> */
    public function givenFeedbackResponseIds(): array
    {
        return app(ReadsSubjectResponses::class)->respondentIds($this->getMorphClass(), (string) $this->getKey());
    }

    public function hasSubmittedFeedbackFor(Model $subject, ?string $formId = null): bool
    {
        return app(ReadsSubjectResponses::class)->hasSubmitted(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $formId,
        );
    }

    public function latestFeedbackResponseIdFor(Model $subject, ?string $formId = null): ?string
    {
        return app(ReadsSubjectResponses::class)->latestId(
            $this->getMorphClass(),
            (string) $this->getKey(),
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $formId,
        );
    }
}
