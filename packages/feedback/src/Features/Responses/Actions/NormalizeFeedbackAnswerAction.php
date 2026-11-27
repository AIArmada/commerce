<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Contracts\AnswerNormalizer;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;

final class NormalizeFeedbackAnswerAction
{
    public function __construct(
        private readonly AnswerNormalizer $normalizer,
    ) {}

    public function execute(FeedbackQuestion $question, mixed $value): array
    {
        return $this->normalizer->normalize($question->type, $value);
    }
}
