<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Features\Responses\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;
use AIArmada\Feedback\Features\Analytics\Queries\FeedbackAnalyticsQuery;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Support\Collection;

it('calculates known NPS, CSAT, distribution, and completion values', function (): void {
    $createForm = app(CreateFeedbackFormAction::class);
    $structure = app(SaveFeedbackFormStructureAction::class);
    $submit = app(SubmitFeedbackResponseAction::class);

    $npsForm = $createForm->execute(new CreateFeedbackFormData(
        name: 'NPS Analytics',
        status: 'published',
        visibility: 'public',
    ));
    $npsQuestion = $structure->saveQuestion($npsForm->id, [
        'key' => 'recommendation',
        'type' => 'nps',
        'label' => 'Recommend',
        'is_required' => true,
    ]);

    foreach ([10, 8, 5] as $score) {
        $submit->execute(new SubmitFeedbackResponseData(
            formId: $npsForm->id,
            answers: new Collection([
                new SubmittedAnswerData($npsQuestion->id, $npsQuestion->key, $score),
            ]),
            isAnonymous: true,
        ));
    }

    $csatForm = $createForm->execute(new CreateFeedbackFormData(
        name: 'CSAT Analytics',
        status: 'published',
        visibility: 'public',
    ));
    $csatQuestion = $structure->saveQuestion($csatForm->id, [
        'key' => 'satisfaction',
        'type' => 'csat',
        'label' => 'Satisfaction',
        'is_required' => true,
    ]);

    foreach ([5, 3, 2] as $score) {
        $submit->execute(new SubmitFeedbackResponseData(
            formId: $csatForm->id,
            answers: new Collection([
                new SubmittedAnswerData($csatQuestion->id, $csatQuestion->key, $score),
            ]),
            isAnonymous: true,
        ));
    }

    FeedbackResponse::query()->create([
        'feedback_form_id' => $csatForm->id,
        'status' => 'draft',
        'is_anonymous' => true,
    ]);

    $analytics = app(FeedbackAnalyticsQuery::class);
    $nps = app(FeedbackAnalyticsQuery::class)->nps($npsForm);
    $csat = app(FeedbackAnalyticsQuery::class)->csat($csatForm);

    expect($nps->score)->toBe(0)
        ->and($nps->promoterCount)->toBe(1)
        ->and($nps->passiveCount)->toBe(1)
        ->and($nps->detractorCount)->toBe(1)
        ->and($csat->score)->toBe(33.33)
        ->and($csat->average)->toBe(3.33)
        ->and($analytics->distributionForQuestion($csatForm, 'satisfaction'))
        ->toBe([2 => 1, 3 => 1, 5 => 1])
        ->and($analytics->completionRate($csatForm))->toBe(75.0);
});

it('keys the dashboard cache by owner', function (): void {
    $otherOwner = User::query()->create([
        'name' => 'Dashboard Cache Owner',
        'email' => 'dashboard-cache-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $defaultDashboard = app(FeedbackAnalyticsQuery::class)->dashboard();
    $otherDashboard = OwnerContext::withOwner($otherOwner, function (): array {
        app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Other Dashboard Form'));

        return app(FeedbackAnalyticsQuery::class)->dashboard();
    });

    expect($defaultDashboard['overview']['total_forms'])->toBe(0)
        ->and($otherDashboard['overview']['total_forms'])->toBe(1)
        ->and(config('feedback.analytics.dashboard_cache_ttl'))->toBe(30);
});

it('excludes cross-owner joined answers from rating calculations', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Calculator Owner A',
        'email' => 'calculator-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Calculator Owner B',
        'email' => 'calculator-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    [$form, $question, $response] = OwnerContext::withOwner($ownerA, function (): array {
        $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Scoped Analytics'));
        $question = app(SaveFeedbackFormStructureAction::class)->saveQuestion($form->id, [
            'key' => 'rating',
            'type' => 'rating',
            'label' => 'Rating',
        ]);
        $response = FeedbackResponse::query()->create([
            'feedback_form_id' => $form->id,
            'status' => 'submitted',
            'is_anonymous' => true,
        ]);
        FeedbackAnswer::query()->create([
            'feedback_response_id' => $response->id,
            'feedback_question_id' => $question->id,
            'number_value' => 5,
        ]);

        return [$form, $question, $response];
    });

    OwnerContext::withOwner($ownerB, function () use ($question, $response): void {
        FeedbackAnswer::query()->create([
            'feedback_response_id' => $response->id,
            'feedback_question_id' => $question->id,
            'number_value' => 99,
        ]);
    });

    $distribution = OwnerContext::withOwner(
        $ownerA,
        fn (): array => app(FeedbackAnalyticsQuery::class)->distributionForQuestion($form, 'rating'),
    );

    expect($distribution)->toBe([5 => 1]);
});

it('keeps reviewed responses in analytics and dashboard totals', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Reviewed analytics'));
    $question = app(SaveFeedbackFormStructureAction::class)->saveQuestion($form->id, [
        'key' => 'rating',
        'type' => 'rating',
        'label' => 'Rating',
    ]);
    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'reviewed',
        'score' => 5,
        'submitted_at' => now(),
        'is_anonymous' => true,
    ]);
    FeedbackAnswer::query()->create([
        'feedback_response_id' => $response->id,
        'feedback_question_id' => $question->id,
        'number_value' => 5,
    ]);

    $analytics = app(FeedbackAnalyticsQuery::class);
    $dashboard = $analytics->dashboard();

    expect($analytics->completionRate($form))->toBe(100.0)
        ->and($analytics->distributionForQuestion($form, 'rating'))->toBe([5 => 1])
        ->and($dashboard['overview']['submitted_responses'])->toBe(1)
        ->and(array_sum($dashboard['response_trend']))->toBe(1);
});

it('returns only bounded comment projections without contact answers or response metadata', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Comment privacy'));
    $structure = app(SaveFeedbackFormStructureAction::class);
    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'reviewed',
        'is_anonymous' => true,
        'ip_address' => '192.0.2.1',
        'metadata' => ['private' => 'not for dashboard'],
    ]);

    foreach (['short_text' => 'Useful comment', 'email' => 'private@example.test', 'phone' => '+60123456789'] as $type => $value) {
        $question = $structure->saveQuestion($form->id, [
            'key' => $type,
            'type' => $type,
            'label' => $type,
        ]);
        FeedbackAnswer::query()->create([
            'feedback_response_id' => $response->id,
            'feedback_question_id' => $question->id,
            'text_value' => $value,
        ]);
    }

    $analytics = app(FeedbackAnalyticsQuery::class);
    $comments = $analytics->latestComments($form);

    expect($comments)->toHaveCount(1)
        ->and(array_keys($comments[0]))->toBe(['id', 'text_value'])
        ->and($comments[0]['text_value'])->toBe('Useful comment')
        ->and($analytics->latestComments($form, 0))->toBe([]);
});
