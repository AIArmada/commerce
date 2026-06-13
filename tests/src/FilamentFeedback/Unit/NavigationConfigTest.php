<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentFeedback\Pages\FeedbackDashboard;
use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use AIArmada\FilamentFeedback\Resources\FeedbackInvitationResource;
use AIArmada\FilamentFeedback\Resources\FeedbackResponseResource;
use AIArmada\FilamentFeedback\Resources\FeedbackTemplateResource;
use AIArmada\FilamentFeedback\Resources\FeedbackTestimonialResource;

uses(TestCase::class);

it('reads feedback navigation group from configuration', function (): void {
    config()->set('filament-feedback.navigation.group', 'Customer Voice');

    expect(FeedbackDashboard::getNavigationGroup())->toBe('Customer Voice')
        ->and(FeedbackFormResource::getNavigationGroup())->toBe('Customer Voice')
        ->and(FeedbackInvitationResource::getNavigationGroup())->toBe('Customer Voice')
        ->and(FeedbackResponseResource::getNavigationGroup())->toBe('Customer Voice')
        ->and(FeedbackTemplateResource::getNavigationGroup())->toBe('Customer Voice')
        ->and(FeedbackTestimonialResource::getNavigationGroup())->toBe('Customer Voice');
});
