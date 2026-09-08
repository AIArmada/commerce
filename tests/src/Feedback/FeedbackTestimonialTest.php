<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\ApproveFeedbackTestimonialAction;
use AIArmada\Feedback\Actions\PublishFeedbackTestimonialAction;
use AIArmada\Feedback\Models\FeedbackTestimonial;
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
