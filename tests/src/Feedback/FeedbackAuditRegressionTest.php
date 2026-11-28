<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Features\Forms\Actions\ArchiveFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\CloseFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Features\Forms\Actions\PublishFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Features\Forms\Actions\UpdateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;
use AIArmada\Feedback\Features\Forms\Policies\FeedbackFormPolicy;
use AIArmada\Feedback\Features\Invitations\Actions\SendFeedbackInvitationAction;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Responses\Queries\CsatCalculator;
use AIArmada\Feedback\Features\Responses\Queries\NpsCalculator;
use AIArmada\Feedback\Features\Responses\Queries\RatingDistributionCalculator;
use AIArmada\Feedback\Features\Responses\Queries\ReadResponseMetricsQuery;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

function feedbackAuditOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

function feedbackAuditResponse(FeedbackForm $form, string $status, array $attributes = []): FeedbackResponse
{
    return FeedbackResponse::query()->create(array_merge([
        'feedback_form_id' => $form->id,
        'status' => $status,
        'is_anonymous' => true,
    ], $attributes));
}

it('enforces one response per respondent for submitted and reviewed statuses at the database level', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Index Form'));
    $respondent = feedbackAuditOwner('Index Respondent');

    feedbackAuditResponse($form, 'submitted', [
        'respondent_type' => $respondent->getMorphClass(),
        'respondent_id' => (string) $respondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]);

    expect(fn () => feedbackAuditResponse($form, 'submitted', [
        'respondent_type' => $respondent->getMorphClass(),
        'respondent_id' => (string) $respondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]))->toThrow(QueryException::class);

    expect(fn () => feedbackAuditResponse($form, 'reviewed', [
        'respondent_type' => $respondent->getMorphClass(),
        'respondent_id' => (string) $respondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]))->toThrow(QueryException::class);

    $reviewedForm = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Index Review Form'));
    $reviewedRespondent = feedbackAuditOwner('Index Review Respondent');

    feedbackAuditResponse($reviewedForm, 'reviewed', [
        'respondent_type' => $reviewedRespondent->getMorphClass(),
        'respondent_id' => (string) $reviewedRespondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]);

    expect(fn () => feedbackAuditResponse($reviewedForm, 'submitted', [
        'respondent_type' => $reviewedRespondent->getMorphClass(),
        'respondent_id' => (string) $reviewedRespondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]))->toThrow(QueryException::class);

    $retryRespondent = feedbackAuditOwner('Index Retry Respondent');

    feedbackAuditResponse($form, 'rejected', [
        'respondent_type' => $retryRespondent->getMorphClass(),
        'respondent_id' => (string) $retryRespondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]);
    feedbackAuditResponse($form, 'spam', [
        'respondent_type' => $retryRespondent->getMorphClass(),
        'respondent_id' => (string) $retryRespondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]);
    feedbackAuditResponse($form, 'submitted', [
        'respondent_type' => $retryRespondent->getMorphClass(),
        'respondent_id' => (string) $retryRespondent->getKey(),
        'enforce_respondent_uniqueness' => true,
    ]);

    expect(FeedbackResponse::query()->where('feedback_form_id', $form->id)->count())->toBe(4)
        ->and(FeedbackResponse::query()->where('feedback_form_id', $reviewedForm->id)->count())->toBe(1);
});

it('keeps owner-disabled mode usable while enabled mode still requires owner context', function (): void {
    config()->set('feedback.owner.enabled', false);

    $policy = new FeedbackFormPolicy;
    $user = feedbackAuditOwner('Policy User');

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue();

    config()->set('feedback.owner.enabled', true);

    $owner = feedbackAuditOwner('Policy Owner');
    $form = OwnerContext::withOwner($owner, fn (): FeedbackForm => app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Policy Form')));

    OwnerContext::withOwner(null, function () use ($policy, $user, $form): void {
        expect($policy->viewAny($user))->toBeTrue()
            ->and($policy->view($user, $form))->toBeFalse();
    });

    OwnerContext::withOwner($owner, fn () => expect($policy->view($user, $form))->toBeTrue());
    OwnerContext::withOwner(feedbackAuditOwner('Other Owner'), fn () => expect($policy->view($user, $form))->toBeFalse());
});

it('rejects resurrection of archived forms', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Archived Form'));
    $form = app(ArchiveFeedbackFormAction::class)->execute($form);

    expect($form->status)->toBe(FeedbackFormStatus::Archived)
        ->and(fn () => app(PublishFeedbackFormAction::class)->execute($form))->toThrow(RuntimeException::class)
        ->and(fn () => app(CloseFeedbackFormAction::class)->execute($form))->toThrow(RuntimeException::class)
        ->and(fn () => app(UpdateFeedbackFormAction::class)->execute($form, ['status' => 'draft']))->toThrow(RuntimeException::class)
        ->and($form->fresh()->status)->toBe(FeedbackFormStatus::Archived);
});

it('prefers a local template over a same-slug global template and rejects duplicate local slugs', function (): void {
    config()->set('feedback.owner.include_global_templates', true);

    $owner = feedbackAuditOwner('Template Owner');
    $subject = feedbackAuditOwner('Template Subject');

    $global = OwnerContext::withOwner(null, fn (): FeedbackTemplate => FeedbackTemplate::query()->create([
        'name' => 'Global Template',
        'slug' => 'shared-audit-template',
        'purpose' => 'general',
        'status' => 'published',
    ]));

    $local = OwnerContext::withOwner($owner, fn (): FeedbackTemplate => FeedbackTemplate::query()->create([
        'name' => 'Local Template',
        'slug' => 'shared-audit-template',
        'purpose' => 'general',
        'status' => 'published',
    ]));

    $formId = OwnerContext::withOwner($owner, fn (): string => app(CreateFeedbackFormFromTemplateAction::class)
        ->createForSubject($local->slug, $subject->getMorphClass(), (string) $subject->getKey()));

    expect(OwnerContext::withOwner($owner, fn (): FeedbackForm => FeedbackForm::query()->findOrFail($formId))->name)->toBe('Local Template')
        ->and($global->isGlobal())->toBeTrue();

    OwnerContext::withOwner($owner, fn (): FeedbackTemplate => FeedbackTemplate::query()->create([
        'name' => 'Duplicate Local',
        'slug' => 'shared-audit-template',
        'purpose' => 'general',
        'status' => 'published',
    ]));

    expect(fn () => OwnerContext::withOwner($owner, fn (): string => app(CreateFeedbackFormFromTemplateAction::class)
        ->createForSubject($local->slug, $subject->getMorphClass(), (string) $subject->getKey())))
        ->toThrow(MultipleRecordsFoundException::class);
});

it('rejects unsaved or blank invitation references before writing', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Invitation Reference Form'));

    expect(fn () => app(SendFeedbackInvitationAction::class)->execute(formId: ' ', email: 'blank@example.com'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SendFeedbackInvitationAction::class)->execute(
            formId: $form->id,
            recipient: new User(['name' => 'Unsaved']),
            email: 'unsaved@example.com',
        ))
        ->toThrow(InvalidArgumentException::class)
        ->and(FeedbackInvitation::query()->count())->toBe(0);
});

it('creates a draft response without a respondent and keeps identifier columns nullable', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Anonymous Form'));

    $response = feedbackAuditResponse($form, 'draft');

    expect($response->respondent_type)->toBeNull()
        ->and($response->respondent_id)->toBeNull()
        ->and(Str::isUuid($response->id))->toBeTrue();
});

it('excludes answers backed by foreign-owner responses from every answer metric', function (): void {
    $owner = feedbackAuditOwner('Metrics Owner');
    $foreignOwner = feedbackAuditOwner('Foreign Metrics Owner');

    $form = OwnerContext::withOwner($owner, fn (): FeedbackForm => app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Scoped Metrics Form')));

    $structure = app(SaveFeedbackFormStructureAction::class);

    $npsQuestion = OwnerContext::withOwner($owner, fn (): FeedbackQuestion => $structure->saveQuestion($form->id, [
        'key' => 'nps',
        'type' => 'nps',
        'label' => 'NPS',
    ]));

    $commentQuestion = OwnerContext::withOwner($owner, fn (): FeedbackQuestion => $structure->saveQuestion($form->id, [
        'key' => 'comment',
        'type' => 'short_text',
        'label' => 'Comment',
    ]));

    $foreignResponse = OwnerContext::withOwner($foreignOwner, fn (): FeedbackResponse => feedbackAuditResponse($form, 'submitted', [
        'score' => 10,
    ]));

    OwnerContext::withOwner($owner, function () use ($foreignResponse, $npsQuestion, $commentQuestion): void {
        FeedbackAnswer::query()->create([
            'feedback_response_id' => $foreignResponse->id,
            'feedback_question_id' => $npsQuestion->id,
            'score' => 10,
            'number_value' => 5,
        ]);

        FeedbackAnswer::query()->create([
            'feedback_response_id' => $foreignResponse->id,
            'feedback_question_id' => $commentQuestion->id,
            'score' => 5,
            'number_value' => 4,
            'text_value' => 'Foreign owner comment',
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($form): void {
        $nps = app(NpsCalculator::class)->calculate($form->id, 'nps');
        $csat = app(CsatCalculator::class)->calculate($form->id, 'nps');

        expect($nps->responseCount)->toBe(0)
            ->and($nps->score)->toBeNull()
            ->and($csat->responseCount)->toBe(0)
            ->and(app(RatingDistributionCalculator::class)->calculate($form->id, 'nps'))->toBe([])
            ->and(app(ReadResponseMetricsQuery::class)->latestComments($form->id))->toBe([]);
    });
});
