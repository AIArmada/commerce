<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Forms\Models\FeedbackSection;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;

it('isolates all feedback models by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Feedback Isolation A',
        'email' => 'feedback-isolation-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Feedback Isolation B',
        'email' => 'feedback-isolation-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $createAggregate = function (User $owner, string $name): array {
        return OwnerContext::withOwner($owner, function () use ($name): array {
            $form = app(CreateFeedbackFormAction::class)
                ->execute(new CreateFeedbackFormData(name: $name));
            $structure = app(SaveFeedbackFormStructureAction::class);
            $section = $structure->saveSection($form->id, ['title' => 'Section']);
            $question = $structure->saveQuestion($form->id, [
                'key' => 'score',
                'type' => 'rating',
                'label' => 'Score',
            ]);
            $option = $structure->saveOption($question->id, [
                'label' => 'Five',
                'value' => 'five',
                'score' => 5,
            ]);
            $response = FeedbackResponse::query()->create([
                'feedback_form_id' => $form->id,
                'status' => 'submitted',
                'is_anonymous' => true,
            ]);
            $answer = FeedbackAnswer::query()->create([
                'feedback_response_id' => $response->id,
                'feedback_question_id' => $question->id,
                'number_value' => 5,
                'score' => 5,
            ]);
            $invitation = FeedbackInvitation::query()->create([
                'feedback_form_id' => $form->id,
                'token_hash' => hash('sha256', $name),
                'status' => 'pending',
            ]);
            $template = FeedbackTemplate::query()->create([
                'name' => $name . ' Template',
                'slug' => mb_strtolower(str_replace(' ', '-', $name)),
                'purpose' => 'general',
                'status' => 'draft',
            ]);
            $testimonial = FeedbackTestimonial::query()->create([
                'feedback_response_id' => $response->id,
                'feedback_answer_id' => $answer->id,
                'quote' => $name . ' quote',
                'status' => 'pending',
            ]);

            return [
                'form' => $form->id,
                'section' => $section->id,
                'question' => $question->id,
                'option' => $option->id,
                'response' => $response->id,
                'answer' => $answer->id,
                'invitation' => $invitation->id,
                'template' => $template->id,
                'testimonial' => $testimonial->id,
            ];
        });
    };

    $ownerAIds = $createAggregate($ownerA, 'Owner A');
    $ownerBIds = $createAggregate($ownerB, 'Owner B');

    OwnerContext::withOwner($ownerA, function () use ($ownerAIds, $ownerBIds): void {
        foreach ([
            FeedbackForm::class => 'form',
            FeedbackSection::class => 'section',
            FeedbackQuestion::class => 'question',
            FeedbackQuestionOption::class => 'option',
            FeedbackResponse::class => 'response',
            FeedbackAnswer::class => 'answer',
            FeedbackInvitation::class => 'invitation',
            FeedbackTemplate::class => 'template',
            FeedbackTestimonial::class => 'testimonial',
        ] as $modelClass => $key) {
            expect($modelClass::query()->find($ownerAIds[$key]))->not->toBeNull()
                ->and($modelClass::query()->find($ownerBIds[$key]))->toBeNull();
        }
    });
});
