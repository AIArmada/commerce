<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

use InvalidArgumentException;

final readonly class SubmitFeedbackResponseData
{
    /**
     * @param  list<SubmittedAnswerData>  $answers
     */
    public function __construct(
        public string $formId,
        public array $answers,
        public ?string $respondentType = null,
        public ?string $respondentId = null,
        public ?string $invitationId = null,
        public bool $isAnonymous = false,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public array $metadata = [],
    ) {
        if (! array_is_list($answers)) {
            throw new InvalidArgumentException('Submitted answers must be a list.');
        }

        foreach ($answers as $answer) {
            if (! $answer instanceof SubmittedAnswerData) {
                throw new InvalidArgumentException('Each submitted answer must be a SubmittedAnswerData instance.');
            }
        }
    }
}
