<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
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

    /**
     * @param  list<QuestionDefinition>  $questions
     * @return list<QuestionDefinition>
     */
    public function execute(array $questions, array $answerValues): array
    {

        $evaluatedValues = $answerValues;

        $visibleQuestions = $this->visibilityEvaluator->filterHiddenQuestions(
            $questions,
            $evaluatedValues,
        );

        $rules = [];

        foreach ($visibleQuestions as $question) {
            $rules["answers.{$question->key}"] = $this->ruleBuilder->build($question);
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
