<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final readonly class SubmittedAnswerData
{
    public function __construct(
        public string $questionId,
        public string $questionKey,
        public mixed $value,
    ) {}
}
