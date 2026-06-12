<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Support\ValidationRuleBuilder;
use AIArmada\Feedback\Support\VisibilityRuleEvaluator;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class ValidateFeedbackAnswersAction
{
    public function __construct(
        private readonly ValidationRuleBuilder $ruleBuilder,
        private readonly VisibilityRuleEvaluator $visibilityEvaluator,
    ) {}

    public function execute(FeedbackForm $form, array $answerValues): array
    {
        $form->loadMissing('questions');

        $evaluatedValues = $answerValues;

        $visibleQuestions = $this->visibilityEvaluator->filterHiddenQuestions(
            $form->questions->all(),
            $evaluatedValues,
        );

        $rules = [];
        $questionMap = [];

        foreach ($visibleQuestions as $question) {
            $rules["answers.{$question->key}"] = $this->ruleBuilder->build($question);
            $questionMap[$question->key] = $question;
        }

        $validator = Validator::make(
            ['answers' => $evaluatedValues],
            $rules,
        );

        if ($validator->fails()) {
            throw new RuntimeException('Validation failed: ' . json_encode($validator->errors()->toArray()));
        }

        return $visibleQuestions;
    }
}
