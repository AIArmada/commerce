<?php

declare(strict_types=1);

use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;
use Illuminate\Support\Collection;

it('submits and scores each supported question type', function (
    string $type,
    mixed $value,
    float $expectedScore,
    array $options = [],
    array $settings = [],
    array $scoringRules = [],
): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(
        name: "Scoring {$type}",
        status: 'published',
        visibility: 'public',
    ));
    $structure = app(SaveFeedbackFormStructureAction::class);
    $question = $structure->saveQuestion($form->id, [
        'key' => 'score',
        'type' => $type,
        'label' => 'Score',
        'is_required' => true,
        'is_scored' => true,
        'settings' => $settings,
        'scoring_rules' => $scoringRules,
    ]);

    foreach ($options as $option) {
        $structure->saveOption($question->id, $option);
    }

    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([
            new SubmittedAnswerData($question->id, $question->key, $value),
        ]),
        isAnonymous: true,
    ));

    expect((float) $response->fresh()->score)->toBe($expectedScore);
})->with([
    'short text map' => [
        'short_text', 'excellent', 5.0, [], [], ['map' => ['excellent' => 5]],
    ],
    'single choice' => [
        'single_choice', 'excellent', 5.0,
        [['label' => 'Excellent', 'value' => 'excellent', 'score' => 5]],
    ],
    'multiple choice' => [
        'multiple_choice', ['speed', 'quality'], 7.0,
        [
            ['label' => 'Speed', 'value' => 'speed', 'score' => 3],
            ['label' => 'Quality', 'value' => 'quality', 'score' => 4],
        ],
    ],
    'dropdown' => [
        'dropdown', 'good', 4.0, [['label' => 'Good', 'value' => 'good', 'score' => 4]],
    ],
    'rating' => ['rating', 5, 5.0, [], ['min' => 1, 'max' => 5]],
    'star rating' => ['star_rating', 4, 4.0, [], ['min' => 1, 'max' => 5]],
    'scale' => ['scale', 3, 3.0, [], ['min' => 1, 'max' => 5]],
    'nps' => ['nps', 9, 9.0],
    'csat' => ['csat', 5, 5.0],
    'yes no' => [
        'yes_no', true, 2.0,
        [
            ['label' => 'Yes', 'value' => '1', 'score' => 2],
            ['label' => 'No', 'value' => '0', 'score' => 0],
        ],
    ],
    'boolean' => [
        'boolean', true, 2.0,
        [
            ['label' => 'Yes', 'value' => '1', 'score' => 2],
            ['label' => 'No', 'value' => '0', 'score' => 1],
        ],
    ],
    'matrix' => [
        'matrix', 'good', 3.0, [['label' => 'Good', 'value' => 'good', 'score' => 3]],
    ],
    'likert' => [
        'likert', 'agree', 4.0, [['label' => 'Agree', 'value' => 'agree', 'score' => 4]],
    ],
    'ranking' => [
        'ranking', ['first', 'second'], 5.0,
        [
            ['label' => 'First', 'value' => 'first', 'score' => 3],
            ['label' => 'Second', 'value' => 'second', 'score' => 2],
        ],
    ],
]);
