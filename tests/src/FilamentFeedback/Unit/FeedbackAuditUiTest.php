<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentFeedback\FilamentFeedbackTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages\FeedbackFormAnalytics;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource\Pages\ManageFeedbackFormBuilder;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Exports\FeedbackAnswersExport;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Exports\FeedbackResponsesExport;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Resources\FeedbackResponseResource;
use AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Exports\FeedbackTestimonialsExport;
use AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Resources\FeedbackTestimonialResource;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(FilamentFeedbackTestCase::class);

function feedbackUiTable(string $resource): Table
{
    $host = Mockery::mock(HasTable::class);
    $host->shouldReceive('getTableRecordKey')->andReturnUsing(
        static fn (mixed $record): string => is_array($record) ? implode('.', $record) : (string) $record->getKey(),
    );

    return $resource::table(Table::make($host));
}

it('redacts anonymous respondent types in the response table', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Redaction Form'));
    $anonymous = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
        'respondent_type' => 'App\\Models\\User',
        'respondent_id' => 'anonymous-id',
    ]);
    $identified = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => false,
        'respondent_type' => 'App\\Models\\User',
        'respondent_id' => 'identified-id',
    ]);

    $column = feedbackUiTable(FeedbackResponseResource::class)->getColumn('respondent_type');

    expect($column->record($anonymous)->getState())->toBeNull()
        ->and($column->record($identified)->getState())->toBe('App\\Models\\User');
});

it('renders enum-backed badges without a string type error', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Badge Form'));
    $formTable = feedbackUiTable(FeedbackFormResource::class);

    $state = static fn (string $column): mixed => $formTable->getColumn($column)
        ->formatState($formTable->getColumn($column)->record($form)->getState());

    expect($state('status'))->toBe('Draft')
        ->and($state('purpose'))->toBe('General')
        ->and($state('visibility'))->toBe('Private');
});

it('scopes the testimonial exporter to FeedbackTestimonial and the active owner', function (): void {
    expect(FeedbackTestimonialsExport::getModel())->toBe(FeedbackTestimonial::class)
        ->and(FeedbackTestimonialsExport::modifyQuery(FeedbackTestimonial::query())->toSql())
        ->toContain('owner_type');
});

it('guards the builder and analytics pages with the record owner context trait', function (): void {
    expect(class_uses_recursive(ManageFeedbackFormBuilder::class))->toContain(VerifiesRecordOwnerContext::class)
        ->and(class_uses_recursive(FeedbackFormAnalytics::class))->toContain(VerifiesRecordOwnerContext::class);
});

it('authorizes form and response transition actions with their record abilities', function (): void {
    $owner = User::query()->firstOrFail();
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Authorized Form'));
    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
        'is_anonymous' => true,
    ]);

    $this->actingAs($owner);

    $publish = collect(feedbackUiTable(FeedbackFormResource::class)->getActions())
        ->first(fn ($action): bool => $action->getName() === 'publish');
    $review = collect(feedbackUiTable(FeedbackResponseResource::class)->getActions())
        ->first(fn ($action): bool => $action->getName() === 'review');

    expect($publish->record($form)->getAuthorizationResponse()->allowed())->toBeTrue()
        ->and($review->record($response)->getAuthorizationResponse()->allowed())->toBeTrue();

    $other = User::query()->create([
        'name' => 'Other UI Owner',
        'email' => 'other-ui-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $foreignForm = OwnerContext::withOwner($other, fn (): FeedbackForm => app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Foreign Form')));

    expect($publish->record($foreignForm)->getAuthorizationResponse()->allowed())->toBeFalse();
});

it('projects export labels with owner-scoped joins instead of correlated subqueries', function (): void {
    $responsesSql = FeedbackResponsesExport::modifyQuery(FeedbackResponse::query())->toSql();
    $answersSql = FeedbackAnswersExport::modifyQuery(FeedbackAnswer::query())->toSql();

    expect($responsesSql)->toContain('left join (select "id" as "export_form_id", "name" from "feedback_forms"')
        ->and($answersSql)->toContain('left join (select "id" as "export_response_id", "feedback_form_id" as "response_form_id" from "feedback_responses"')
        ->and($answersSql)->toContain('left join (select "id" as "export_form_id", "name" from "feedback_forms"')
        ->and($answersSql)->toContain('left join (select "id" as "export_question_id", "feedback_form_id" as "question_form_id", "key", "label" from "feedback_questions"');
});

it('retains record IDs and values when joining export labels', function (): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(name: 'Export Form'));
    $question = app(SaveFeedbackFormStructureAction::class)->saveQuestion($form->id, [
        'key' => 'comment',
        'type' => 'short_text',
        'label' => 'Comment',
    ]);
    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => 'submitted',
    ]);
    $answer = FeedbackAnswer::query()->create([
        'feedback_response_id' => $response->id,
        'feedback_question_id' => $question->id,
        'text_value' => 'Exported answer',
    ]);

    $exportedResponse = FeedbackResponsesExport::modifyQuery(FeedbackResponse::query())
        ->where('id', $response->id)->sole();
    $exportedAnswer = FeedbackAnswersExport::modifyQuery(FeedbackAnswer::query())
        ->where('id', $answer->id)->sole();

    expect($exportedResponse->id)->toBe($response->id)
        ->and($exportedResponse->getAttribute('form_name'))->toBe('Export Form')
        ->and($exportedAnswer->id)->toBe($answer->id)
        ->and($exportedAnswer->text_value)->toBe('Exported answer')
        ->and($exportedAnswer->getAttribute('question_label'))->toBe('Comment');
});

it('formats exported status columns as plain string values', function (): void {
    $responseStatus = collect(FeedbackResponsesExport::getColumns())
        ->first(fn ($column): bool => $column->getName() === 'status');
    $testimonialStatus = collect(FeedbackTestimonialsExport::getColumns())
        ->first(fn ($column): bool => $column->getName() === 'status');

    expect($responseStatus->formatState(FeedbackResponseStatus::Submitted))->toBe('submitted')
        ->and($testimonialStatus->formatState(FeedbackTestimonialStatus::Pending))->toBe('pending');
});

it('authorizes response and testimonial exports for the active owner only', function (): void {
    $owner = User::query()->firstOrFail();
    $this->actingAs($owner);

    $exportAction = function (string $resource): ExportBulkAction {
        return collect(feedbackUiTable($resource)->getBulkActions())
            ->first(fn ($action): bool => $action instanceof ExportBulkAction);
    };

    expect($exportAction(FeedbackResponseResource::class)->isAuthorized())->toBeTrue()
        ->and($exportAction(FeedbackTestimonialResource::class)->isAuthorized())->toBeTrue();

    app()->forgetInstance(OwnerResolverInterface::class);

    expect($exportAction(FeedbackResponseResource::class)->isAuthorized())->toBeFalse()
        ->and($exportAction(FeedbackTestimonialResource::class)->isAuthorized())->toBeFalse();
});
