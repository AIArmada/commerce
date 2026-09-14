<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Support;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;

/**
 * Request-scoped memo for the analytics dashboard payload.
 *
 * Bound as scoped so every widget on a dashboard render shares one
 * underlying dashboard() computation instead of recomputing per widget.
 */
final class FeedbackDashboardMemo
{
    /** @var array<string, mixed>|null */
    private ?array $dashboard = null;

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return $this->dashboard ??= app(FeedbackAnalyticsService::class)->dashboard();
    }
}
