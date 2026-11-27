<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

use Illuminate\Support\Collection;

final readonly class SubmitFeedbackResponseData
{
    /**
     * @param  Collection<int, SubmittedAnswerData>  $answers
     */
    public function __construct(
        public string $formId,
        public Collection $answers,
        public ?string $respondentType = null,
        public ?string $respondentId = null,
        public ?string $invitationId = null,
        public bool $isAnonymous = false,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public array $metadata = [],
    ) {}
}
