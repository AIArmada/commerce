<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Features\Forms\Actions\SaveFeedbackFormStructureAction;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Actions\ApproveFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Api\ExtractsTestimonials;
use AIArmada\Feedback\Features\Testimonials\Actions\PublishFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('requires approval and permission before a testimonial is public', function (): void {
    $testimonial = FeedbackTestimonial::query()->create([
        'quote' => 'A useful quote',
        'status' => 'pending',
    ]);

    expect(fn () => app(PublishFeedbackTestimonialAction::class)->execute($testimonial))
        ->toThrow(RuntimeException::class, 'Only approved');

    $approved = app(ApproveFeedbackTestimonialAction::class)->execute($testimonial);

    expect($approved->status->value)->toBe('approved')
        ->and($approved->approved_at)->not->toBeNull()
        ->and(FeedbackTestimonial::published()->count())->toBe(0)
        ->and(fn () => app(PublishFeedbackTestimonialAction::class)->execute($approved))
        ->toThrow(RuntimeException::class, 'Permission');

    $approved->forceFill(['permission_given_at' => CarbonImmutable::now()])->save();
    $published = app(PublishFeedbackTestimonialAction::class)->execute($approved);

    expect($published->status->value)->toBe('published')
        ->and($published->published_at)->not->toBeNull()
        ->and(FeedbackTestimonial::published()->pluck('id')->all())->toContain($published->id);
});

it('blocks testimonial lifecycle writes from another owner', function (): void {
    $otherOwner = User::query()->create([
        'name' => 'Other Testimonial Owner',
        'email' => 'testimonial-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $testimonial = OwnerContext::withOwner($otherOwner, fn (): FeedbackTestimonial => FeedbackTestimonial::query()->create([
        'quote' => 'Private quote',
        'status' => 'pending',
    ]));

    expect(fn () => app(ApproveFeedbackTestimonialAction::class)->execute($testimonial))
        ->toThrow(AuthorizationException::class);
});

it('extracts only completed responses and never duplicates a testimonial', function (FeedbackResponseStatus $status): void {
    $form = app(CreateFeedbackFormAction::class)->execute(new CreateFeedbackFormData(
        name: 'Testimonial source',
        purpose: 'testimonial_collection',
        status: 'published',
    ));
    $question = app(SaveFeedbackFormStructureAction::class)->saveQuestion($form->id, [
        'key' => 'quote',
        'type' => 'short_text',
        'label' => 'Quote',
    ]);
    $response = FeedbackResponse::query()->create([
        'feedback_form_id' => $form->id,
        'status' => $status,
        'is_anonymous' => true,
    ]);
    $response->answers()->create([
        'feedback_question_id' => $question->id,
        'text_value' => 'Useful feedback',
    ]);
    $extract = app(ExtractsTestimonials::class);
    $id = $extract->execute($response->id);

    if (in_array($status, [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed], true)) {
        expect($id)->toBeString()
            ->and($extract->execute($response->id))->toBe($id)
            ->and(FeedbackTestimonial::query()->where('feedback_response_id', $response->id)->count())->toBe(1);
    } else {
        expect($id)->toBeNull()
            ->and(FeedbackTestimonial::query()->where('feedback_response_id', $response->id)->count())->toBe(0);
    }
})->with(FeedbackResponseStatus::cases());
