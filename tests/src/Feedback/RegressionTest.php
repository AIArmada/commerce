<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\ArchiveFeedbackFormAction;
use AIArmada\Feedback\Actions\CloseFeedbackFormAction;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\DuplicateFeedbackFormAction;
use AIArmada\Feedback\Actions\ExtractFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\HideFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\MarkFeedbackInvitationOpenedAction;
use AIArmada\Feedback\Actions\MarkFeedbackResponseAsSpamAction;
use AIArmada\Feedback\Actions\PublishFeedbackFormAction;
use AIArmada\Feedback\Actions\RejectFeedbackResponseAction;
use AIArmada\Feedback\Actions\RejectFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\ResolveFeedbackInvitationTokenAction;
use AIArmada\Feedback\Actions\ReviewFeedbackResponseAction;
use AIArmada\Feedback\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Actions\SendFeedbackInvitationAction;
use AIArmada\Feedback\Actions\StartFeedbackResponseAction;
use AIArmada\Feedback\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Analytics\CsatCalculator;
use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use AIArmada\Feedback\Analytics\NpsCalculator;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Data\CsatResultData;
use AIArmada\Feedback\Data\NpsResultData;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackFormCreated;
use AIArmada\Feedback\Events\FeedbackInvitationCreated;
use AIArmada\Feedback\Events\FeedbackInvitationSent;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackQuestion;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTemplate;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use AIArmada\Feedback\Support\AnswerValueNormalizer;
use AIArmada\Feedback\Support\ScoreCalculator;
use AIArmada\Feedback\Traits\ReceivesFeedback;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class RepairReceivesFeedbackUser extends User
{
    use ReceivesFeedback;
}

function feedbackRegressionUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

function regressionPublishedForm(string $name, array $extra = []): FeedbackForm
{
    $args = array_merge([
        'name' => $name,
        'status' => 'published',
        'visibility' => 'public',
    ], $extra);

    return app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(...$args));
}

it('duplicates section-less questions with their options', function (): void {
    $form = regressionPublishedForm('Duplicate Source');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $section = $structure->saveSection($form->id, ['title' => 'Section']);
    $sectioned = $structure->saveQuestion($form->id, [
        'key' => 'sectioned', 'type' => 'short_text', 'label' => 'Sectioned',
        'feedback_section_id' => $section->id,
    ]);
    $structure->saveOption($sectioned->id, ['label' => 'A', 'value' => 'a']);
    $loose = $structure->saveQuestion($form->id, [
        'key' => 'loose', 'type' => 'short_text', 'label' => 'Loose',
    ]);
    $structure->saveOption($loose->id, ['label' => 'B', 'value' => 'b']);

    $copy = app(DuplicateFeedbackFormAction::class)->execute($form);

    expect($copy->questions()->count())->toBe(2)
        ->and($copy->questions()->whereNull('feedback_section_id')->count())->toBe(1)
        ->and($copy->questions()->where('key', 'loose')->firstOrFail()->options()->count())->toBe(1)
        ->and($copy->questions()->where('key', 'sectioned')->firstOrFail()->options()->count())->toBe(1);
});

it('creates a full form from a template through the trait', function (): void {
    Event::fake([FeedbackFormCreated::class]);
    $subject = feedbackRegressionUser('Trait Subject');
    $traitSubject = RepairReceivesFeedbackUser::query()->whereKey($subject->id)->firstOrFail();
    FeedbackTemplate::query()->create([
        'name' => 'Trait Template',
        'slug' => 'trait-template',
        'purpose' => 'general',
        'status' => 'published',
        'definition' => [
            'sections' => [[
                'title' => 'Experience',
                'questions' => [[
                    'key' => 'rating', 'type' => 'rating', 'label' => 'Rating',
                    'options' => [['label' => 'Great', 'value' => 'great', 'score' => 5]],
                ]],
            ]],
        ],
    ]);

    $form = $traitSubject->createFeedbackFormFromTemplate('trait-template');

    expect($form->sections()->count())->toBe(1)
        ->and($form->questions()->count())->toBe(1)
        ->and($form->questions()->firstOrFail()->options()->count())->toBe(1)
        ->and($form->subject_type)->toBe($traitSubject->getMorphClass())
        ->and((string) $form->subject_id)->toBe((string) $traitSubject->getKey());

    Event::assertDispatched(FeedbackFormCreated::class);
});

it('aggregates per-question NPS and CSAT over answer scores', function (): void {
    $form = regressionPublishedForm('Per Question Scores');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $nps = $structure->saveQuestion($form->id, [
        'key' => 'recommendation', 'type' => 'nps', 'label' => 'Recommend',
    ]);
    $csat = $structure->saveQuestion($form->id, [
        'key' => 'satisfaction', 'type' => 'csat', 'label' => 'Satisfaction',
    ]);

    app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([
            new SubmittedAnswerData($nps->id, $nps->key, 10),
            new SubmittedAnswerData($csat->id, $csat->key, 2),
        ]),
        isAnonymous: true,
    ));

    $npsResult = app(NpsCalculator::class)->calculate($form, 'recommendation');
    $csatResult = app(CsatCalculator::class)->calculate($form, 'satisfaction');

    // Response total is 12 (promoter range); per-question aggregates must not see it.
    expect($npsResult->promoterCount)->toBe(1)
        ->and($npsResult->responseCount)->toBe(1)
        ->and($csatResult->unsatisfiedCount)->toBe(1)
        ->and($csatResult->score)->toBe(0.0)
        ->and($csatResult->responseCount)->toBe(1);
});

it('guards response, form, invitation, and testimonial lifecycle writes by owner', function (): void {
    $owner = feedbackRegressionUser('Lifecycle Owner');
    [$form, $response, $invitation, $testimonial] = OwnerContext::withOwner($owner, function (): array {
        $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Guarded'));
        $response = FeedbackResponse::query()->create([
            'feedback_form_id' => $form->id, 'status' => 'submitted', 'is_anonymous' => true,
        ]);
        $invitation = FeedbackInvitation::query()->create([
            'feedback_form_id' => $form->id,
            'token_hash' => hash('sha256', 'guarded-invitation'),
            'status' => 'sent',
        ]);
        $testimonial = FeedbackTestimonial::query()->create([
            'feedback_response_id' => $response->id, 'quote' => 'Guarded', 'status' => 'pending',
        ]);

        return [$form, $response, $invitation, $testimonial];
    });

    $attempts = [
        fn () => app(MarkFeedbackResponseAsSpamAction::class)->execute($response),
        fn () => app(ReviewFeedbackResponseAction::class)->execute($response),
        fn () => app(RejectFeedbackResponseAction::class)->execute($response),
        fn () => app(PublishFeedbackFormAction::class)->execute($form),
        fn () => app(CloseFeedbackFormAction::class)->execute($form),
        fn () => app(ArchiveFeedbackFormAction::class)->execute($form),
        fn () => app(MarkFeedbackInvitationOpenedAction::class)->execute($invitation),
        fn () => app(RejectFeedbackTestimonialAction::class)->execute($testimonial),
        fn () => app(HideFeedbackTestimonialAction::class)->execute($testimonial),
        fn () => app(ExtractFeedbackTestimonialAction::class)->execute($response),
    ];

    foreach ($attempts as $attempt) {
        expect($attempt)->toThrow(AuthorizationException::class);
    }

    expect($response->fresh()->status)->toBe(FeedbackResponseStatus::Submitted)
        ->and($form->fresh()->status->value)->toBe('draft');
});

it('returns the reviewed response on resubmit but allows resubmit after rejection', function (): void {
    $respondent = feedbackRegressionUser('Review Resubmit');
    $form = regressionPublishedForm('Review Resubmit Form', ['isOneResponsePerRespondent' => true]);
    $data = fn (): SubmitFeedbackResponseData => new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );

    $first = app(SubmitFeedbackResponseAction::class)->execute($data());
    app(ReviewFeedbackResponseAction::class)->execute($first);

    $again = app(SubmitFeedbackResponseAction::class)->execute($data());

    expect($again->is($first))->toBeTrue()
        ->and(FeedbackResponse::query()->where('feedback_form_id', $form->id)->count())->toBe(1);

    app(RejectFeedbackResponseAction::class)->execute($first);

    $fresh = app(SubmitFeedbackResponseAction::class)->execute($data());

    expect($fresh->is($first))->toBeFalse()
        ->and($fresh->status)->toBe(FeedbackResponseStatus::Submitted)
        ->and(FeedbackResponse::query()->where('feedback_form_id', $form->id)->count())->toBe(2);
});

it('sums per-question maxima for the response max score', function (): void {
    $form = regressionPublishedForm('Max Score Form');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $rating = $structure->saveQuestion($form->id, [
        'key' => 'rating', 'type' => 'rating', 'label' => 'Rating',
        'is_scored' => true, 'settings' => ['min' => 1, 'max' => 5],
    ]);
    $choice = $structure->saveQuestion($form->id, [
        'key' => 'choice', 'type' => 'single_choice', 'label' => 'Choice', 'is_scored' => true,
    ]);
    $structure->saveOption($choice->id, ['label' => 'Low', 'value' => 'low', 'score' => 1]);
    $structure->saveOption($choice->id, ['label' => 'High', 'value' => 'high', 'score' => 4]);

    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([
            new SubmittedAnswerData($rating->id, $rating->key, 4),
            new SubmittedAnswerData($choice->id, $choice->key, 'high'),
        ]),
        isAnonymous: true,
    ));

    expect((float) $response->fresh()->score)->toBe(8.0)
        ->and((float) $response->fresh()->max_score)->toBe(9.0);
});

it('separates completed responses from pending review', function (): void {
    $form = regressionPublishedForm('Review Metrics');
    FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id, 'status' => 'submitted', 'is_anonymous' => true,
    ]);
    FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id, 'status' => 'reviewed', 'is_anonymous' => true,
    ]);
    FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id, 'status' => 'draft', 'is_anonymous' => true,
    ]);

    $live = app(FeedbackAnalyticsService::class)->calculateLive($form);

    expect($live->totalResponses)->toBe(3)
        ->and($live->completedResponses)->toBe(2)
        ->and($live->pendingReview)->toBe(1);
});

it('calculates live analytics in a single query', function (): void {
    $form = regressionPublishedForm('Single Query Analytics');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    app(FeedbackAnalyticsService::class)->calculateLive($form);

    expect($queries)->toBe(1);
});

it('scores preloaded choice questions without extra queries', function (): void {
    $form = regressionPublishedForm('Preloaded Scoring');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $question = $structure->saveQuestion($form->id, [
        'key' => 'choice', 'type' => 'multiple_choice', 'label' => 'Choice', 'is_scored' => true,
    ]);
    $structure->saveOption($question->id, ['label' => 'A', 'value' => 'a', 'score' => 3]);
    $structure->saveOption($question->id, ['label' => 'B', 'value' => 'b', 'score' => 4]);
    $question->load('options');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $score = app(ScoreCalculator::class)->calculateScore($question, ['a', 'b']);

    expect($score)->toBe(7.0)
        ->and($queries)->toBe(0);
});

it('rejects choice values outside the defined options', function (): void {
    $form = regressionPublishedForm('Allowlist Form');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $single = $structure->saveQuestion($form->id, [
        'key' => 'single', 'type' => 'single_choice', 'label' => 'Single', 'is_required' => true,
    ]);
    $structure->saveOption($single->id, ['label' => 'Ok', 'value' => 'ok']);
    $multi = $structure->saveQuestion($form->id, [
        'key' => 'multi', 'type' => 'multiple_choice', 'label' => 'Multi',
    ]);
    $structure->saveOption($multi->id, ['label' => 'Ok', 'value' => 'ok']);

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([
            new SubmittedAnswerData($single->id, $single->key, 'hacker'),
            new SubmittedAnswerData($multi->id, $multi->key, ['ok', 'hacker']),
        ]),
        isAnonymous: true,
    )))->toThrow(RuntimeException::class, 'Validation failed');
});

it('accepts scalar and array matrix answers within the defined options', function (): void {
    $form = regressionPublishedForm('Matrix Form');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $matrix = $structure->saveQuestion($form->id, [
        'key' => 'matrix', 'type' => 'matrix', 'label' => 'Matrix',
    ]);
    $structure->saveOption($matrix->id, ['label' => 'Good', 'value' => 'good']);

    $scalar = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([new SubmittedAnswerData($matrix->id, $matrix->key, 'good')]),
        isAnonymous: true,
    ));
    $array = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([new SubmittedAnswerData($matrix->id, $matrix->key, ['good'])]),
        isAnonymous: true,
    ));

    expect($scalar->status)->toBe(FeedbackResponseStatus::Submitted)
        ->and($array->status)->toBe(FeedbackResponseStatus::Submitted)
        ->and(fn () => app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
            formId: $form->id,
            answers: new Collection([new SubmittedAnswerData($matrix->id, $matrix->key, 'evil')]),
            isAnonymous: true,
        )))->toThrow(RuntimeException::class, 'Validation failed');
});

it('marks invitations sent and dispatches the sent event', function (): void {
    Event::fake([FeedbackInvitationCreated::class, FeedbackInvitationSent::class]);
    $form = regressionPublishedForm('Sent Invitation Form');

    $result = app(SendFeedbackInvitationAction::class)->execute(form: $form, email: 'sent@example.com');
    $invitation = $result['invitation'];

    expect($invitation->status)->toBe(FeedbackInvitationStatus::Sent)
        ->and($invitation->sent_at)->not->toBeNull();

    Event::assertDispatched(FeedbackInvitationCreated::class);
    Event::assertDispatched(FeedbackInvitationSent::class);
});

it('rejects respondents outside the allowlist', function (): void {
    config()->set('feedback.security.respondent_allowlist', [User::class]);
    $form = regressionPublishedForm('Allowlist Respondent Form');
    $otherForm = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Respondent Form'));
    $user = feedbackRegressionUser('Allowed Respondent');

    expect(fn () => app(StartFeedbackResponseAction::class)->execute(
        form: $form,
        respondentType: $otherForm->getMorphClass(),
        respondentId: (string) $otherForm->getKey(),
    ))->toThrow(InvalidArgumentException::class, 'not allowed');

    $response = app(StartFeedbackResponseAction::class)->execute(
        form: $form,
        respondentType: $user->getMorphClass(),
        respondentId: (string) $user->getKey(),
    );

    expect($response->status)->toBe(FeedbackResponseStatus::Draft);
});

it('enforces question key uniqueness and type availability', function (): void {
    $form = regressionPublishedForm('Unique Keys Form');
    $structure = app(SaveFeedbackFormStructureAction::class);
    $structure->saveQuestion($form->id, [
        'key' => 'dup', 'type' => 'short_text', 'label' => 'First',
    ]);

    expect(fn () => $structure->saveQuestion($form->id, [
        'key' => 'dup', 'type' => 'short_text', 'label' => 'Second',
    ]))->toThrow(InvalidArgumentException::class, 'already used')
        ->and(fn () => $structure->saveQuestion($form->id, [
            'key' => 'nope', 'type' => 'not_a_type', 'label' => 'Bad',
        ]))->toThrow(InvalidArgumentException::class, 'not available')
        ->and(fn () => $structure->saveQuestion($form->id, [
            'key' => 'file', 'type' => 'file_upload', 'label' => 'File',
        ]))->toThrow(InvalidArgumentException::class, 'not available');
});

it('enforces the question key unique index at the database level', function (): void {
    $form = regressionPublishedForm('DB Unique Keys');

    FeedbackQuestion::query()->create([
        'feedback_form_id' => $form->id, 'key' => 'dbdup', 'type' => 'short_text', 'label' => 'One',
    ]);

    expect(fn () => FeedbackQuestion::query()->create([
        'feedback_form_id' => $form->id, 'key' => 'dbdup', 'type' => 'short_text', 'label' => 'Two',
    ]))->toThrow(QueryException::class);
});

it('rejects disabled question types at submission validation', function (): void {
    $form = regressionPublishedForm('Disabled Type Form');
    $question = FeedbackQuestion::query()->create([
        'feedback_form_id' => $form->id, 'key' => 'sig', 'type' => 'signature', 'label' => 'Sign',
    ]);

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([new SubmittedAnswerData($question->id, $question->key, 'x')]),
        isAnonymous: true,
    )))->toThrow(RuntimeException::class, 'Validation failed');
});

it('refuses to start responses on closed forms or invalid invitations', function (): void {
    $form = regressionPublishedForm('Start Guard Form');
    app(CloseFeedbackFormAction::class)->execute($form);

    expect(fn () => app(StartFeedbackResponseAction::class)->execute(form: $form, isAnonymous: true))
        ->toThrow(RuntimeException::class, 'not accepting');

    $open = regressionPublishedForm('Start Guard Open Form');
    $cancelled = FeedbackInvitation::query()->create([
        'feedback_form_id' => $open->id,
        'token_hash' => hash('sha256', 'cancelled-start'),
        'status' => 'cancelled',
    ]);

    expect(fn () => app(StartFeedbackResponseAction::class)->execute(
        form: $open,
        invitation: $cancelled,
        isAnonymous: true,
    ))->toThrow(RuntimeException::class, 'cancelled');
});

it('extracts testimonials only from sanitized long or short text answers', function (): void {
    $form = regressionPublishedForm('Testimonial Extract Form');
    $form->forceFill(['purpose' => 'testimonial_collection'])->save();
    $structure = app(SaveFeedbackFormStructureAction::class);
    $email = $structure->saveQuestion($form->id, [
        'key' => 'email', 'type' => 'email', 'label' => 'Email',
    ]);
    $story = $structure->saveQuestion($form->id, [
        'key' => 'story', 'type' => 'long_text', 'label' => 'Story',
    ]);

    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([
            new SubmittedAnswerData($email->id, $email->key, 'user@example.com'),
            new SubmittedAnswerData($story->id, $story->key, '<b>Great</b> product<script>alert(1)</script>'),
        ]),
        isAnonymous: true,
    ));

    $testimonial = FeedbackTestimonial::query()->where('feedback_response_id', $response->id)->firstOrFail();

    expect($testimonial->quote)->toBe('Great productalert(1)')
        ->and($testimonial->feedback_answer_id)->not->toBeNull();

    $answer = FeedbackAnswer::query()->whereKey($testimonial->feedback_answer_id)->firstOrFail();

    expect($answer->feedback_question_id)->toBe($story->id);
});

it('skips testimonial extraction for non-text answers and missing forms', function (): void {
    $form = regressionPublishedForm('No Text Extract Form');
    $form->forceFill(['purpose' => 'testimonial_collection'])->save();
    $question = app(SaveFeedbackFormStructureAction::class)->saveQuestion($form->id, [
        'key' => 'email', 'type' => 'email', 'label' => 'Email',
    ]);
    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([new SubmittedAnswerData($question->id, $question->key, 'a@example.com')]),
        isAnonymous: true,
    ));

    expect(FeedbackTestimonial::query()->where('feedback_response_id', $response->id)->count())->toBe(0);

    $orphan = FeedbackResponse::query()->create([
        'feedback_form_id' => (string) Str::uuid(),
        'status' => 'submitted',
        'is_anonymous' => true,
    ]);

    expect(app(ExtractFeedbackTestimonialAction::class)->execute($orphan))->toBeNull();
});

it('persists response metadata from the submission payload', function (): void {
    $form = regressionPublishedForm('Metadata Form');

    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        isAnonymous: true,
        metadata: ['source' => 'repair'],
    ));

    expect($response->fresh()->metadata)->toBe(['source' => 'repair']);
});

it('throttles invitation resolution per client IP across tokens', function (): void {
    config()->set('feedback.security.invitation_rate_limit.max_attempts', 2);
    config()->set('feedback.security.invitation_rate_limit.decay_seconds', 60);

    $resolve = app(ResolveFeedbackInvitationTokenAction::class);

    expect(fn () => $resolve->execute(bin2hex(random_bytes(32))))
        ->toThrow(RuntimeException::class, 'Invalid invitation token')
        ->and(fn () => $resolve->execute(bin2hex(random_bytes(32))))
        ->toThrow(RuntimeException::class, 'Invalid invitation token')
        ->and(fn () => $resolve->execute(bin2hex(random_bytes(32))))
        ->toThrow(RuntimeException::class, 'Too many invitation token attempts');
});

it('returns calculated NPS and CSAT results from the analytics service', function (): void {
    $form = regressionPublishedForm('Service Results');

    expect(app(FeedbackAnalyticsService::class)->nps($form))->toBeInstanceOf(NpsResultData::class)
        ->and(app(FeedbackAnalyticsService::class)->csat($form))->toBeInstanceOf(CsatResultData::class);
});

it('builds the dashboard response trend on sqlite', function (): void {
    $form = regressionPublishedForm('Trend Form');
    FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
        'submitted_at' => CarbonImmutable::now(),
    ]);

    $dashboard = app(FeedbackAnalyticsService::class)->dashboard();

    expect($dashboard['response_trend'])->toBeArray()
        ->and(array_sum($dashboard['response_trend']))->toBe(1);
});

it('normalizes unsafe numbers and dates to null', function (): void {
    $normalizer = app(AnswerValueNormalizer::class);

    $number = $normalizer->normalize(new FeedbackQuestion(['type' => 'number']), 'abc');
    $scored = $normalizer->normalize(new FeedbackQuestion(['type' => 'rating']), 'abc');
    $datetime = $normalizer->normalize(new FeedbackQuestion(['type' => 'datetime']), 'not-a-date');

    expect($number['number_value'])->toBeNull()
        ->and($scored['number_value'])->toBeNull()
        ->and($datetime['datetime_value'])->toBeNull();

    $valid = $normalizer->normalize(new FeedbackQuestion(['type' => 'number']), '4.5');

    expect($valid['number_value'])->toBe(4.5);
});

it('truncates overlong IP addresses on submit', function (): void {
    $form = regressionPublishedForm('IP Truncate Form');

    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        isAnonymous: true,
        ipAddress: str_repeat('1', 300),
    ));

    expect(mb_strlen((string) $response->fresh()->ip_address))->toBe(255);
});

it('persists invitation expiry when submission fails on an expired invitation', function (): void {
    $form = regressionPublishedForm('Expiry Persist Form');
    $invitation = FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', 'expired-submit'),
        'status' => 'sent',
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect(fn () => app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        invitationId: $invitation->id,
        isAnonymous: true,
    )))->toThrow(RuntimeException::class, 'expired');

    expect($invitation->fresh()->status)->toBe(FeedbackInvitationStatus::Expired)
        ->and(FeedbackResponse::query()->where('feedback_form_id', $form->id)->count())->toBe(0);
});

it('prunes past-due invitations through the console command', function (): void {
    $form = regressionPublishedForm('Prune Form');
    $due = FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', 'prune-due'),
        'status' => 'sent',
        'expires_at' => CarbonImmutable::now()->subDay(),
    ]);
    $freshInvitation = FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', 'prune-fresh'),
        'status' => 'sent',
        'expires_at' => CarbonImmutable::now()->addDay(),
    ]);

    $this->artisan('feedback:prune-expired-invitations --dry-run')->assertSuccessful();

    expect($due->fresh()->status)->toBe(FeedbackInvitationStatus::Sent);

    $this->artisan('feedback:prune-expired-invitations')->assertSuccessful();

    expect($due->fresh()->status)->toBe(FeedbackInvitationStatus::Expired)
        ->and($freshInvitation->fresh()->status)->toBe(FeedbackInvitationStatus::Sent);
});

it('hides the invitation token hash from serialized output', function (): void {
    $form = regressionPublishedForm('Hidden Hash Form');
    $invitation = FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', 'hidden-hash'),
        'status' => 'pending',
    ]);

    expect($invitation->toArray())->not->toHaveKey('token_hash')
        ->and($invitation->token_hash)->toBe(hash('sha256', 'hidden-hash'));
});

it('applies the configured table prefix to feedback models', function (): void {
    config()->set('feedback.database.table_prefix', 'tenant_');

    expect((new FeedbackForm)->getTable())->toBe('tenant_feedback_forms')
        ->and((new FeedbackResponse)->getTable())->toBe('tenant_feedback_responses');
});

it('averages per-question scores over answers on the trait', function (): void {
    $subject = feedbackRegressionUser('Average Subject');
    $traitSubject = RepairReceivesFeedbackUser::query()->whereKey($subject->id)->firstOrFail();
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(
        name: 'Trait Average',
        status: 'published',
        visibility: 'public',
        subjectType: $traitSubject->getMorphClass(),
        subjectId: (string) $traitSubject->getKey(),
    ));
    $structure = app(SaveFeedbackFormStructureAction::class);
    $q1 = $structure->saveQuestion($form->id, ['key' => 'q1', 'type' => 'nps', 'label' => 'Q1']);
    $q2 = $structure->saveQuestion($form->id, ['key' => 'q2', 'type' => 'nps', 'label' => 'Q2']);

    app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection([
            new SubmittedAnswerData($q1->id, $q1->key, 10),
            new SubmittedAnswerData($q2->id, $q2->key, 0),
        ]),
        isAnonymous: true,
    ));

    // Response total is 10; the q2 answer average must be 0, not 10.
    expect($traitSubject->averageFeedbackScore('q2'))->toBe(0.0)
        ->and($traitSubject->averageFeedbackScore('q1'))->toBe(10.0);
});
