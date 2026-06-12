<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\Pages;

use AIArmada\FilamentFeedback\Resources\FeedbackFormResource;
use Filament\Resources\Pages\Page;

final class ManageFeedbackFormBuilder extends Page
{
    protected static string $resource = FeedbackFormResource::class;

    public function getView(): string
    {
        return 'filament-feedback::pages.feedback-form-builder';
    }
}
