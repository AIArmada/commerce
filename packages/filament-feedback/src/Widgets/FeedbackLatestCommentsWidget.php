<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Models\FeedbackAnswer;
use Filament\Widgets\Widget;

final class FeedbackLatestCommentsWidget extends Widget
{
    public function getView(): string
    {
        return 'filament-feedback::widgets.feedback-latest-comments';
    }

    public function getComments(): array
    {
        $query = OwnerUiScope::apply(
            FeedbackAnswer::query()->with(['response.form', 'question']),
            includeGlobal: false,
        );

        return (clone $query)
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }
}
