<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics\EntryPoints\Filament\Widgets;

use AIArmada\FilamentFeedback\Features\Analytics\Queries\FeedbackDashboardMemo;
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
