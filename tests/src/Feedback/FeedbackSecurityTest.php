<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\CreateFeedbackQuestionAction;
use AIArmada\Feedback\Actions\CreateFeedbackSectionAction;
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
use Illuminate\Auth\Access\AuthorizationException;

it('does not mass assign feedback owner columns', function (string $modelClass): void {
    $model = new $modelClass([
        'owner_type' => 'attacker',
        'owner_id' => 'attacker-id',
    ]);

    expect($model->owner_type)->toBeNull()
        ->and($model->owner_id)->toBeNull();
})->with([
    FeedbackForm::class,
    FeedbackSection::class,
    FeedbackQuestion::class,
    FeedbackQuestionOption::class,
    FeedbackResponse::class,
    FeedbackAnswer::class,
    FeedbackInvitation::class,
    FeedbackTemplate::class,
    FeedbackTestimonial::class,
]);

it('rejects cross-owner child ids', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Feedback Owner A',
        'email' => 'feedback-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Feedback Owner B',
        'email' => 'feedback-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $formB = OwnerContext::withOwner(
        $ownerB,
        fn (): FeedbackForm => app(CreateFeedbackFormAction::class)
            ->execute(new CreateFeedbackFormData(name: 'Owner B Form')),
    );

    expect(fn () => OwnerContext::withOwner(
        $ownerA,
        fn () => app(CreateFeedbackSectionAction::class)->execute($formB->id, 'Invalid Section'),
    ))->toThrow(AuthorizationException::class);
});

it('rejects sections from another form under the same owner', function (): void {
    $formA = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Form A'));
    $formB = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Form B'));
    $sectionA = app(CreateFeedbackSectionAction::class)
        ->execute($formA->id, 'Section A');

    expect(fn () => app(CreateFeedbackQuestionAction::class)->execute(
        formId: $formB->id,
        key: 'invalid',
        type: 'short_text',
        label: 'Invalid question',
        sectionId: $sectionA->id,
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects cross-owner polymorphic subjects', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Subject Owner A',
        'email' => 'subject-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Subject Owner B',
        'email' => 'subject-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $subject = OwnerContext::withOwner(
        $ownerB,
        fn (): FeedbackForm => app(CreateFeedbackFormAction::class)
            ->execute(new CreateFeedbackFormData(name: 'Protected Subject')),
    );

    expect(fn () => OwnerContext::withOwner(
        $ownerA,
        fn () => app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(
            name: 'Invalid Subject Form',
            subjectType: FeedbackForm::class,
            subjectId: $subject->id,
        )),
    ))->toThrow(AuthorizationException::class);
});
