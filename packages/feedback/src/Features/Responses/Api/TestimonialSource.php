<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Api;

final readonly class TestimonialSource
{
    public function __construct(
        public string $responseId,
        public string $answerId,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $respondentType,
        public ?string $respondentId,
        public string $quote,
    ) {}
}
