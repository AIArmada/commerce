<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Data;

final class SubmittedAnswerData
{
    public function __construct(
        public readonly string $questionId,
        public readonly string $questionKey,
        public readonly mixed $value,
    ) {}
}
