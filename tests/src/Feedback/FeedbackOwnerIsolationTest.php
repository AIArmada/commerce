<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackQuestionOption;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackSection;
use AIArmada\Feedback\Models\FeedbackTemplate;
use AIArmada\Feedback\Models\FeedbackTestimonial;

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
