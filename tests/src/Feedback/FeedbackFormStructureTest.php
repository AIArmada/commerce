<?php

declare(strict_types=1);

use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Features\Forms\Actions\DuplicateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Features\Forms\Actions\UpdateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;

it('saves, updates, creates from templates, and duplicates form structure', function (): void {
    $template = FeedbackTemplate::query()->create([
        'name' => 'Customer Voice Template',
        'slug' => 'customer-voice-template',
        'purpose' => 'general',
        'status' => 'published',
        'definition' => [
            'sections' => [
                [
                    'key' => 'experience',
                    'title' => 'Experience',
                    'questions' => [
                        [
                            'key' => 'rating',
                            'type' => 'rating',
                            'label' => 'Rating',
                            'options' => [
                                ['label' => 'Great', 'value' => 'great', 'score' => 5],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $form = app(CreateFeedbackFormFromTemplateAction::class)->execute($template);
    $section = $form->sections()->firstOrFail();
    $question = $section->questions()->firstOrFail();

    expect($section->title)->toBe('Experience')
        ->and($question->label)->toBe('Rating')
        ->and($question->options()->count())->toBe(1);

    $structure = app(SaveFeedbackFormStructureAction::class);
    $updatedSection = $structure->saveSection($form->id, ['title' => 'Updated Experience'], $section);
    $updatedQuestion = $structure->saveQuestion($form->id, [
        'key' => $question->key,
        'type' => $question->type,
        'label' => 'Updated Rating',
        'feedback_section_id' => $updatedSection->id,
    ], $question);

    expect($updatedSection->fresh()->title)->toBe('Updated Experience')
        ->and($updatedQuestion->fresh()->label)->toBe('Updated Rating');

    $duplicate = app(DuplicateFeedbackFormAction::class)->execute($form);

    expect($duplicate->id)->not->toBe($form->id)
        ->and($duplicate->sections()->count())->toBe(1)
        ->and($duplicate->questions()->count())->toBe(1)
        ->and($duplicate->questions()->firstOrFail()->options()->count())->toBe(1);
});

it('supports template creation duplication and form lifecycle when owner scoping is disabled', function (): void {
    config(['feedback.owner.enabled' => false]);

    $template = FeedbackTemplate::query()->create([
        'name' => 'Unscoped Template',
        'slug' => 'unscoped-template',
        'purpose' => 'general',
        'status' => 'published',
        'definition' => [],
    ]);
    $form = app(CreateFeedbackFormFromTemplateAction::class)->execute($template);
    $copy = app(DuplicateFeedbackFormAction::class)->execute($form);
    $update = app(UpdateFeedbackFormAction::class);

    expect($copy->id)->not->toBe($form->id)
        ->and($update->execute($copy, ['status' => 'published'])->status)->toBe(FeedbackFormStatus::Published)
        ->and($update->execute($copy, ['status' => 'closed'])->status)->toBe(FeedbackFormStatus::Closed)
        ->and($update->execute($copy, ['status' => 'archived'])->status)->toBe(FeedbackFormStatus::Archived);
});
