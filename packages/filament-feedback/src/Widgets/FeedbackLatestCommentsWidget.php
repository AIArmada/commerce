<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Widgets;

use AIArmada\FilamentFeedback\Support\FeedbackDashboardMemo;
use Filament\Widgets\Widget;

final class FeedbackLatestCommentsWidget extends Widget
{
    public function getView(): string
    {
        return 'filament-feedback::widgets.feedback-latest-comments';
    }

    public function getComments(): array
    {
        return app(FeedbackDashboardMemo::class)->dashboard()['latest_comments'];
    }
}
