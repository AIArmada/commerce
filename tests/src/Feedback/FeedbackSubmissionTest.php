<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Actions\StartFeedbackResponseAction;
use AIArmada\Feedback\Actions\SubmitFeedbackResponseAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Data\SubmitFeedbackResponseData;
use AIArmada\Feedback\Data\SubmittedAnswerData;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
    $questionA = app(SaveFeedbackFormStructureAction::class)->saveQuestion($formA->id, [
        'key' => 'comment',
        'type' => 'short_text',
        'label' => 'Comment',
    ]);

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
    $question = app(SaveFeedbackFormStructureAction::class)->saveQuestion($form->id, [
        'key' => 'nps',
        'type' => 'nps',
        'label' => 'Recommend us',
        'is_required' => true,
        'is_scored' => true,
    ]);

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

it('reuses one submitted response per respondent', function (): void {
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

    $firstResponse = app(SubmitFeedbackResponseAction::class)->execute($data);
    $secondResponse = app(SubmitFeedbackResponseAction::class)->execute($data);

    expect($secondResponse->is($firstResponse))->toBeTrue()
        ->and(FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('respondent_type', $data->respondentType)
            ->where('respondent_id', $data->respondentId)
            ->count())->toBe(1);
});

it('reuses an open draft when starting twice for a respondent', function (): void {
    $respondent = User::query()->create([
        'name' => 'Draft Respondent',
        'email' => 'draft-respondent-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $form = publishedFeedbackForm('Draft Reuse Form');

    $firstResponse = app(StartFeedbackResponseAction::class)->execute(
        form: $form,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );
    $secondResponse = app(StartFeedbackResponseAction::class)->execute(
        form: $form,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );

    expect($secondResponse->is($firstResponse))->toBeTrue()
        ->and($secondResponse->status)->toBe(FeedbackResponseStatus::Draft)
        ->and(FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('respondent_type', $respondent->getMorphClass())
            ->where('respondent_id', $respondent->getKey())
            ->count())->toBe(1);
});

it('submits an existing open draft', function (): void {
    $respondent = User::query()->create([
        'name' => 'Stale Draft Respondent',
        'email' => 'stale-draft-respondent-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $form = publishedFeedbackForm('Stale Draft Form', isOneResponsePerRespondent: true);
    $startResponse = app(StartFeedbackResponseAction::class)->execute(
        form: $form,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );

    $response = app(SubmitFeedbackResponseAction::class)->execute(new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    ));

    expect($response->is($startResponse))->toBeTrue()
        ->and($response->status)->toBe(FeedbackResponseStatus::Submitted)
        ->and(FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('respondent_type', $respondent->getMorphClass())
            ->where('respondent_id', $respondent->getKey())
            ->count())->toBe(1);
});

it('allows multiple submitted responses when one-response mode is disabled', function (): void {
    $respondent = User::query()->create([
        'name' => 'Multiple Response Respondent',
        'email' => 'multiple-response-respondent-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $form = publishedFeedbackForm('Multiple Response Form');
    $data = new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );

    $firstResponse = app(SubmitFeedbackResponseAction::class)->execute($data);
    $secondResponse = app(SubmitFeedbackResponseAction::class)->execute($data);

    expect($secondResponse->is($firstResponse))->toBeFalse()
        ->and(FeedbackResponse::query()
            ->where('feedback_form_id', $form->id)
            ->where('respondent_type', $respondent->getMorphClass())
            ->where('respondent_id', $respondent->getKey())
            ->where('status', FeedbackResponseStatus::Submitted)
            ->count())->toBe(2);
});

it('returns one submitted response when submissions race', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency tests.');
    }

    Event::fake([FeedbackResponseStarted::class, FeedbackResponseSubmitted::class]);
    config()->set('feedback.features.testimonials', false);

    $respondent = User::query()->create([
        'name' => 'Concurrent Respondent',
        'email' => 'concurrent-respondent-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $form = publishedFeedbackForm('Concurrent Form', isOneResponsePerRespondent: true);
    $data = new SubmitFeedbackResponseData(
        formId: $form->id,
        answers: new Collection,
        respondentType: $respondent->getMorphClass(),
        respondentId: (string) $respondent->getKey(),
    );
    $originalDatabase = config('database.connections.testing.database');

    if (! is_string($originalDatabase)) {
        throw new RuntimeException('The testing database path must be a string.');
    }

    $databasePath = feedbackConcurrencyCreateDatabaseCopy();

    try {
        feedbackConcurrencyUseDatabase($databasePath);

        $results = feedbackConcurrencyRunParallelAttempts($databasePath, function () use ($data): array {
            $response = app(SubmitFeedbackResponseAction::class)->execute($data);

            return [
                'response_id' => (string) $response->getKey(),
                'status' => $response->status->value,
            ];
        });
        $successfulResults = array_values(array_filter(
            $results,
            fn (array $result): bool => $result['success'] === true,
        ));

        expect($successfulResults)->toHaveCount(2)
            ->and(array_unique(array_map(
                fn (array $result): string => $result['result']['response_id'],
                $successfulResults,
            )))->toHaveCount(1)
            ->and(FeedbackResponse::query()
                ->where('feedback_form_id', $form->id)
                ->where('respondent_type', $respondent->getMorphClass())
                ->where('respondent_id', $respondent->getKey())
                ->where('status', FeedbackResponseStatus::Submitted)
                ->count())->toBe(1);
    } finally {
        feedbackConcurrencyRestoreDatabase($databasePath, $originalDatabase);
    }
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

/**
 * @param  Closure(int): array<string, mixed>  $attempt
 * @return list<array{success: bool, result?: array<string, mixed>, exception?: string, message?: string}>
 */
function feedbackConcurrencyRunParallelAttempts(string $databasePath, Closure $attempt): array
{
    $barrierPath = feedbackConcurrencyTemporaryPath('feedback-barrier-');
    $readyPaths = [
        feedbackConcurrencyTemporaryPath('feedback-ready-'),
        feedbackConcurrencyTemporaryPath('feedback-ready-'),
    ];
    $resultPaths = [
        feedbackConcurrencyTemporaryPath('feedback-result-'),
        feedbackConcurrencyTemporaryPath('feedback-result-'),
    ];
    $processIds = [];

    try {
        foreach ([0, 1] as $attemptNumber) {
            $processId = pcntl_fork();

            if ($processId === -1) {
                throw new RuntimeException('Unable to fork a feedback concurrency attempt.');
            }

            if ($processId === 0) {
                file_put_contents($readyPaths[$attemptNumber], 'ready', LOCK_EX);

                try {
                    config()->set('database.connections.testing.database', $databasePath);
                    DB::purge('testing');
                    DB::connection('testing')->statement('PRAGMA busy_timeout = 10000');

                    while (! is_file($barrierPath)) {
                        usleep(1000);
                    }

                    $result = [
                        'success' => true,
                        'result' => $attempt($attemptNumber),
                    ];
                } catch (Throwable $exception) {
                    $result = [
                        'success' => false,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ];
                }

                file_put_contents($resultPaths[$attemptNumber], json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);

                exit(0);
            }

            $processIds[] = $processId;
        }

        $deadline = microtime(true) + 10;

        while (! is_file($readyPaths[0]) || ! is_file($readyPaths[1])) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Feedback concurrency attempts did not reach the barrier.');
            }

            usleep(1000);
        }

        file_put_contents($barrierPath, 'go', LOCK_EX);

        foreach ($processIds as $processId) {
            pcntl_waitpid($processId, $status);
        }

        $results = [];

        foreach ($resultPaths as $resultPath) {
            $json = file_get_contents($resultPath);

            if ($json === false) {
                throw new RuntimeException('A feedback concurrency attempt did not write a result.');
            }

            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($result)) {
                throw new RuntimeException('A feedback concurrency result was not an array.');
            }

            $results[] = $result;
        }

        return $results;
    } finally {
        foreach ($processIds as $processId) {
            $status = 0;
            $state = pcntl_waitpid($processId, $status, WNOHANG);

            if ($state === 0 && function_exists('posix_kill')) {
                posix_kill($processId, SIGTERM);
                pcntl_waitpid($processId, $status);
            }
        }

        foreach ([$barrierPath, ...$readyPaths, ...$resultPaths] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

function feedbackConcurrencyTemporaryPath(string $prefix): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix);

    if ($path === false) {
        throw new RuntimeException('Unable to create a feedback concurrency path.');
    }

    unlink($path);

    return $path;
}

function feedbackConcurrencyCreateDatabaseCopy(): string
{
    $databasePath = feedbackConcurrencyTemporaryPath('feedback-database-');
    $connection = DB::connection('testing');

    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $escapedPath = str_replace("'", "''", $databasePath);
    $connection->statement("VACUUM INTO '{$escapedPath}'");

    return $databasePath;
}

function feedbackConcurrencyUseDatabase(string $databasePath): void
{
    config()->set('database.connections.testing.database', $databasePath);
    DB::purge('testing');
    DB::connection('testing')->statement('PRAGMA journal_mode = WAL');
    DB::connection('testing')->statement('PRAGMA busy_timeout = 10000');
}

function feedbackConcurrencyRestoreDatabase(string $databasePath, string $originalDatabase): void
{
    config()->set('database.connections.testing.database', $originalDatabase);
    DB::purge('testing');

    if (is_file($databasePath)) {
        unlink($databasePath);
    }
}
