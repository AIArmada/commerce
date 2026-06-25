<?php

declare(strict_types=1);

use AIArmada\Feedback\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackTemplate;

it('rolls back template form creation when a nested definition is invalid', function (): void {
    $template = FeedbackTemplate::query()->create([
        'name' => 'Broken Template',
        'slug' => 'broken-template',
        'purpose' => 'general',
        'status' => 'published',
        'definition' => [
            'sections' => [[
                'title' => 'Section',
                'questions' => [[
                    'key' => 'choice',
                    'type' => 'single_choice',
                    'label' => 'Choice',
                    'options' => [[
                        'value' => 'missing-label',
                    ]],
                ]],
            ]],
        ],
    ]);

    expect(fn () => app(CreateFeedbackFormFromTemplateAction::class)->execute($template))
        ->toThrow(ErrorException::class);

    expect(FeedbackForm::query()->count())->toBe(0);
});
