<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Responses\Extensions;

use AIArmada\FilamentFeedback\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentFeedback\Features\Responses\EntryPoints\Filament\Resources\FeedbackResponseResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            1 => config('filament-feedback.resources.enabled.feedback_response', true) ? FeedbackResponseResource::class : null,
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
