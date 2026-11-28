<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Invitations\Extensions;

use AIArmada\FilamentFeedback\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentFeedback\Features\Invitations\EntryPoints\Filament\Resources\FeedbackInvitationResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            2 => config('filament-feedback.resources.enabled.feedback_invitation', true) ? FeedbackInvitationResource::class : null,
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
