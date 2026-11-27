<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;

interface AnswerNormalizer
{
    public function normalize(FeedbackQuestion $question, mixed $value): array;
}
