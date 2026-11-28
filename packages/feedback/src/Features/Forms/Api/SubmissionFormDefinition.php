<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

final readonly class SubmissionFormDefinition
{
    /** @param list<QuestionDefinition> $questions */
    public function __construct(
        public string $id,
        public string $status,
        public ?string $opensAt,
        public ?string $closesAt,
        public bool $isAnonymousAllowed,
        public bool $isLoginRequired,
        public bool $isOneResponsePerRespondent,
        public ?string $subjectType,
        public ?string $subjectId,
        public array $questions,
    ) {}
}
