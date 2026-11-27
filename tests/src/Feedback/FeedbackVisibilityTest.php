<?php

declare(strict_types=1);

use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
use AIArmada\Feedback\Features\Responses\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Support\VisibilityRuleEvaluator;

it('excludes hidden questions from submission validation', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(
        name: 'Visibility Form',
        status: 'published',
        visibility: 'public',
    ));
    $structure = app(SaveFeedbackFormStructureAction::class);
    $condition = $structure->saveQuestion($form->id, [
        'key' => 'condition',
        'type' => 'short_text',
        'label' => 'Condition',
        'is_required' => true,
    ]);
    $followUp = $structure->saveQuestion($form->id, [
        'key' => 'follow_up',
        'type' => 'short_text',
        'label' => 'Follow up',
        'is_required' => true,
        'visibility_rules' => [
            'show_if' => [
                'question_key' => 'condition',
                'operator' => '=',
                'value' => 'show',
            ],
        ],
    ]);

    app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: [
            new SubmittedAnswerData($condition->id, $condition->key, 'hide'),
        ],
        isAnonymous: true,
    ));

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: [
            new SubmittedAnswerData($condition->id, $condition->key, 'show'),
        ],
        isAnonymous: true,
    )))->toThrow(RuntimeException::class, 'Validation failed')
        ->and($followUp->fresh()->visibility_rules['show_if']['operator'])->toBe('=');
});

it('evaluates supported visibility operators', function (): void {
    $question = new QuestionDefinition(
        id: 'question',
        key: 'follow_up',
        type: 'short_text',
        isRequired: true,
        isScored: false,
        visibilityRules: [
            'show_if' => [
                'question_key' => 'tags',
                'operator' => 'in',
                'value' => 'vip',
            ],
        ],
    );

    $evaluator = app(VisibilityRuleEvaluator::class);

    expect($evaluator->isVisible($question, ['tags' => ['vip', 'new']]))->toBeTrue()
        ->and($evaluator->isVisible($question, ['tags' => ['new']]))->toBeFalse();
});
