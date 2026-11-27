<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
use AIArmada\Feedback\Support\ValidationRuleBuilder;
use AIArmada\Feedback\Support\VisibilityRuleEvaluator;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final readonly class ValidateFeedbackAnswersAction
{
    public function __construct(
        private ValidationRuleBuilder $ruleBuilder,
        private VisibilityRuleEvaluator $visibilityEvaluator,
    ) {}

    /**
     * @param  list<QuestionDefinition>  $questions
     * @return list<QuestionDefinition>
     */
    public function execute(array $questions, array $answerValues): array
    {

        $visibleQuestions = $this->visibilityEvaluator->filterHiddenQuestions(
            $questions,
            $answerValues,
        );

        $rules = [];
        $values = [];
        $attributes = [];

        foreach ($visibleQuestions as $index => $question) {
            $attribute = "answers.{$index}";
            $rules[$attribute] = $this->ruleBuilder->build($question);
            $attributes[$attribute] = $question->key;

            if (array_key_exists($question->key, $answerValues)) {
                $values[$index] = $answerValues[$question->key];
            }
        }

        $validator = Validator::make(
            ['answers' => $values],
            $rules,
            [],
            $attributes,
        );

        if ($validator->fails()) {
            throw new RuntimeException('Validation failed: ' . json_encode($validator->errors()->toArray()));
        }

        return $visibleQuestions;
    }
}
