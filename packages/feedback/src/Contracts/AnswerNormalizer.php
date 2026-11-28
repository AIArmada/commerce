<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

interface AnswerNormalizer
{
    public function normalize(string $questionType, mixed $value): array;
}
