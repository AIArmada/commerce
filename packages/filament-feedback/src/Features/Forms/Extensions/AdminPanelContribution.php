<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Forms\Extensions;

use AIArmada\FilamentFeedback\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackFormResource;
use AIArmada\FilamentFeedback\Features\Forms\EntryPoints\Filament\Resources\FeedbackTemplateResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            0 => config('filament-feedback.resources.enabled.feedback_form', true) ? FeedbackFormResource::class : null,
            3 => config('filament-feedback.resources.enabled.feedback_template', true) ? FeedbackTemplateResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }

    public function widgets(): array
    {
        return [];
    }

    public function pages(): array
    {
        return [];
    }
}
