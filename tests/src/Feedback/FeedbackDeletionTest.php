<?php

declare(strict_types=1);

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\CreateFeedbackQuestionAction;
use AIArmada\Feedback\Actions\CreateFeedbackQuestionOptionAction;
use AIArmada\Feedback\Actions\CreateFeedbackSectionAction;
use AIArmada\Feedback\Actions\DeleteFeedbackFormAction;
use AIArmada\Feedback\Actions\DeleteFeedbackQuestionAction;
use AIArmada\Feedback\Actions\DeleteFeedbackSectionAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackSection;
use AIArmada\Feedback\Models\FeedbackTestimonial;

it('deletes the complete feedback form aggregate', function (): void {
    $form = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Delete Aggregate'));
    $section = app(CreateFeedbackSectionAction::class)
        ->execute($form->id, 'Section');
    $question = app(CreateFeedbackQuestionAction::class)->execute(
        formId: $form->id,
        key: 'comment',
        type: 'short_text',
        label: 'Comment',
        sectionId: $section->id,
    );
    app(CreateFeedbackQuestionOptionAction::class)
        ->execute($question->id, 'Option', 'option');

    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
    ]);
    $answer = FeedbackAnswer::query()->create([
        'feedback_response_id' => $response->id,
        'feedback_question_id' => $question->id,
        'text_value' => 'Delete me',
    ]);
    $testimonial = FeedbackTestimonial::query()->create([
        'feedback_response_id' => $response->id,
        'feedback_answer_id' => $answer->id,
        'quote' => 'Delete me',
        'status' => 'pending',
    ]);
    FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', 'delete-invitation'),
        'status' => 'pending',
    ]);
    ContactMethod::query()->create([
        'contactable_type' => $testimonial->getMorphClass(),
        'contactable_id' => $testimonial->id,
        'type' => 'email',
        'purpose' => 'general',
        'value' => 'testimonial@example.com',
    ]);

    app(DeleteFeedbackFormAction::class)->execute($form);

    expect(FeedbackForm::query()->count())->toBe(0)
        ->and(FeedbackSection::query()->count())->toBe(0)
        ->and(FeedbackQuestion::query()->count())->toBe(0)
        ->and(FeedbackQuestionOption::query()->count())->toBe(0)
        ->and(FeedbackResponse::query()->count())->toBe(0)
        ->and(FeedbackAnswer::query()->count())->toBe(0)
        ->and(FeedbackInvitation::query()->count())->toBe(0)
        ->and(FeedbackTestimonial::query()->count())->toBe(0)
        ->and(ContactMethod::query()->count())->toBe(0);
});

it('nulls testimonial answer references when deleting a question', function (): void {
    $form = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Delete Question'));
    $question = app(CreateFeedbackQuestionAction::class)->execute(
        formId: $form->id,
        key: 'comment',
        type: 'short_text',
        label: 'Comment',
    );
    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
    ]);
    $answer = FeedbackAnswer::query()->create([
        'feedback_response_id' => $response->id,
        'feedback_question_id' => $question->id,
        'text_value' => 'Keep quote',
    ]);
    $testimonial = FeedbackTestimonial::query()->create([
        'feedback_response_id' => $response->id,
        'feedback_answer_id' => $answer->id,
        'quote' => 'Keep quote',
        'status' => 'pending',
    ]);

    app(DeleteFeedbackQuestionAction::class)->execute($question);

    expect($testimonial->fresh()->feedback_answer_id)->toBeNull()
        ->and(FeedbackAnswer::query()->count())->toBe(0)
        ->and(FeedbackQuestion::query()->count())->toBe(0);
});

it('nulls question section references when deleting a section', function (): void {
    $form = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Delete Section'));
    $section = app(CreateFeedbackSectionAction::class)
        ->execute($form->id, 'Section');
    $question = app(CreateFeedbackQuestionAction::class)->execute(
        formId: $form->id,
        key: 'comment',
        type: 'short_text',
        label: 'Comment',
        sectionId: $section->id,
    );

    app(DeleteFeedbackSectionAction::class)->execute($section);

    expect($question->fresh()->feedback_section_id)->toBeNull()
        ->and(FeedbackSection::query()->count())->toBe(0);
});
