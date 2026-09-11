<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\RecalculateFeedbackFormAnalyticsAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Jobs\RecalculateFeedbackFormAnalyticsJob;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackFormAnalytics;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

it('recalculates stale form analytics through an owner-scoped worker', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Feedback Analytics Owner A',
        'email' => 'feedback-analytics-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Feedback Analytics Owner B',
        'email' => 'feedback-analytics-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $form = OwnerContext::withOwner($ownerA, fn (): FeedbackForm => app(CreateFeedbackFormAction::class)->execute(
        new CreateFeedbackFormData(name: 'Queued analytics'),
    ));

    $response = OwnerContext::withOwner($ownerA, fn (): FeedbackResponse => FeedbackResponse::create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
        'score' => 5,
        'max_score' => 5,
    ]));

    RecalculateFeedbackFormAnalyticsJob::forForm($form)->handle();

    $aggregate = OwnerContext::withOwner($ownerA, fn (): FeedbackFormAnalytics => FeedbackFormAnalytics::query()->firstOrFail());
    expect($aggregate->owner_type)->toBe($ownerA->getMorphClass())
        ->and((string) $aggregate->owner_id)->toBe((string) $ownerA->getKey())
        ->and($aggregate->total_responses)->toBe(1)
        ->and($aggregate->average_score)->toBe('5.00');

    DB::table((new FeedbackResponse)->getTable())
        ->where('id', $response->id)
        ->update(['score' => 3]);

    $stale = OwnerContext::withOwner($ownerA, fn (): FeedbackFormAnalytics => FeedbackFormAnalytics::query()->firstOrFail());
    expect($stale->average_score)->toBe('5.00');

    RecalculateFeedbackFormAnalyticsJob::forForm($form)->handle();

    $fresh = OwnerContext::withOwner($ownerA, fn (): FeedbackFormAnalytics => FeedbackFormAnalytics::query()->firstOrFail());
    expect($fresh->average_score)->toBe('3.00');

    expect(OwnerContext::withOwner($ownerB, fn (): bool => FeedbackFormAnalytics::query()
        ->where('feedback_form_id', $form->id)
        ->exists()))->toBeFalse();

    expect(fn () => OwnerContext::withOwner($ownerB, fn () => app(RecalculateFeedbackFormAnalyticsAction::class)->execute($form)))
        ->toThrow(AuthorizationException::class);
});

it('keeps persisted analytics owner tuples immutable', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Feedback Immutable Owner A',
        'email' => 'feedback-immutable-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Feedback Immutable Owner B',
        'email' => 'feedback-immutable-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $form = OwnerContext::withOwner($ownerA, fn (): FeedbackForm => app(CreateFeedbackFormAction::class)->execute(
        new CreateFeedbackFormData(name: 'Immutable analytics'),
    ));
    OwnerContext::withOwner($ownerA, fn () => app(RecalculateFeedbackFormAnalyticsAction::class)->execute($form));

    $aggregate = OwnerContext::withOwner($ownerA, fn (): FeedbackFormAnalytics => FeedbackFormAnalytics::query()->firstOrFail());
    $aggregate->assignOwner($ownerB);

    expect(fn () => OwnerContext::withOwner($ownerA, fn () => $aggregate->save()))
        ->toThrow(InvalidArgumentException::class, 'cannot be reassigned');
});

it('queues recalculation with the response owner tuple', function (): void {
    Queue::fake();
    $owner = User::query()->create([
        'name' => 'Feedback Queue Owner',
        'email' => 'feedback-queue-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $form = OwnerContext::withOwner($owner, fn (): FeedbackForm => app(CreateFeedbackFormAction::class)->execute(
        new CreateFeedbackFormData(name: 'Queue listener'),
    ));
    $response = OwnerContext::withOwner($owner, fn (): FeedbackResponse => FeedbackResponse::create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
    ]));

    event(new FeedbackResponseSubmitted($response));

    Queue::assertPushed(RecalculateFeedbackFormAnalyticsJob::class, function (RecalculateFeedbackFormAnalyticsJob $job) use ($form, $owner): bool {
        return $job->formId === $form->id
            && $job->ownerType === $owner->getMorphClass()
            && (string) $job->ownerId === (string) $owner->getKey()
            && $job->ownerIsGlobal === false;
    });
});

it('has a guarded aggregate schema and can rerun its migration', function (): void {
    $table = (string) config('feedback.database.tables.form_analytics', 'feedback_form_analytics');
    $migration = require dirname(__DIR__, 3) . '/packages/feedback/database/migrations/2026_09_11_000001_create_feedback_form_analytics_table.php';

    $migration->up();
    $migration->up();

    expect(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::hasColumns($table, [
            'id',
            'feedback_form_id',
            'owner_type',
            'owner_id',
            'total_responses',
            'completed_responses',
            'average_score',
            'max_score',
            'completion_rate',
            'pending_review',
            'rejected',
            'spam',
            'calculated_at',
        ]))->toBeTrue()
        ->and(Schema::hasIndex($table, 'feedback_form_analytics_form_unique'))->toBeTrue();
});
