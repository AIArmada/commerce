<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Support\AnswerValueNormalizer;

final class NormalizeFeedbackAnswerAction
{
    public function __construct(
        private readonly AnswerValueNormalizer $normalizer,
    ) {}

    public function execute(FeedbackQuestion $question, mixed $value): array
    {
        return $this->normalizer->normalize($question, $value);
    }
}
