<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\CreateFeedbackQuestionAction;
use AIArmada\Feedback\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Support\Collection;

it('rejects an invitation issued for another form', function (): void {
    $formA = publishedFeedbackForm('Invitation Form A');
    $formB = publishedFeedbackForm('Invitation Form B');

    $invitation = FeedbackInvitation::query()->create([
        'feedback_form_id' => $formA->id,
        'token_hash' => hash('sha256', 'invitation-a'),
        'status' => FeedbackInvitationStatus::Pending,
    ]);

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute(
        new SubmitFeedbackResponseData(
            formId: $formB->id,
            answers: new Collection,
            invitationId: $invitation->id,
            isAnonymous: true,
        ),
    ))->toThrow(RuntimeException::class, 'does not belong');

    expect($invitation->fresh()->status)->toBe(FeedbackInvitationStatus::Pending)
        ->and(FeedbackResponse::query()->count())->toBe(0);
});

it('rejects question ids from another form', function (): void {
    $formA = publishedFeedbackForm('Question Form A');
    $formB = publishedFeedbackForm('Question Form B');
    $questionA = app(CreateFeedbackQuestionAction::class)->execute(
        formId: $formA->id,
        key: 'comment',
        type: 'short_text',
        label: 'Comment',
    );

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute(
        new SubmitFeedbackResponseData(
            formId: $formB->id,
            answers: collect([
                new SubmittedAnswerData($questionA->id, $questionA->key, 'Injected'),
            ]),
            isAnonymous: true,
        ),
    ))->toThrow(RuntimeException::class, 'outside the selected form');
});

it('submits and scores answers inside the current owner scope', function (): void {
    $form = publishedFeedbackForm('Scored Form');
    $question = app(CreateFeedbackQuestionAction::class)->execute(
        formId: $form->id,
        key: 'nps',
        type: 'nps',
        label: 'Recommend us',
        isRequired: true,
        isScored: true,
    );

    $response = app(SubmitFeedbackResponseAction::class)->execute(
        new SubmitFeedbackResponseData(
            formId: $form->id,
            answers: collect([
                new SubmittedAnswerData($question->id, $question->key, 8),
            ]),
            isAnonymous: true,
        ),
    );

    $answer = FeedbackAnswer::query()->firstOrFail();

    expect((float) $response->fresh()->score)->toBe(8.0)
        ->and($answer->owner_id)->toBe($form->owner_id)
        ->and($answer->feedback_question_id)->toBe($question->id);
});

it('enforces one submitted response per respondent', function (): void {
    $respondent = User::query()->create([
        'name' => 'Feedback Respondent',
        'email' => 'feedback-respondent-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $form = publishedFeedbackForm(
        'Single Response Form',
        isOneResponsePerRespondent: true,
    );
    $data = new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );

    app(SubmitFeedbackResponseAction::class)->execute($data);

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute($data))
        ->toThrow(RuntimeException::class, 'already submitted');
});

function publishedFeedbackForm(
    string $name,
    bool $isOneResponsePerRespondent = false,
): FeedbackForm {
    return app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(
        name: $name,
        status: 'published',
        visibility: 'public',
        isOneResponsePerRespondent: $isOneResponsePerRespondent,
    ));
}
