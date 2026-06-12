<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

use Illuminate\Support\Collection;

final class SubmitFeedbackResponseData
{
    /**
     * @param  Collection<int, SubmittedAnswerData>  $answers
     */
    public function __construct(
        public readonly string $formId,
        public readonly Collection $answers,
        public readonly ?string $respondentType = null,
        public readonly ?string $respondentId = null,
        public readonly ?string $invitationId = null,
        public readonly bool $isAnonymous = false,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly array $metadata = [],
    ) {}
}
