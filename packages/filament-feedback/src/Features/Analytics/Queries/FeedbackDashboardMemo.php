<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Analytics\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Feedback\Features\Analytics\Queries\FeedbackAnalyticsQuery;

/**
 * Request-scoped memo for the analytics dashboard payload.
 *
 * Bound as scoped so every widget on a dashboard render shares one
 * underlying dashboard() computation instead of recomputing per widget.
 */
final class FeedbackDashboardMemo
{
    /** @var array<string, array<string, mixed>> */
    private array $dashboards = [];

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $owner = OwnerContext::resolve();

        if ((bool) config('feedback.owner.enabled', true)) {
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $key = OwnerScopeKey::forOwner($owner);
        } else {
            $key = 'owner-disabled';
        }

        return $this->dashboards[$key] ??= app(FeedbackAnalyticsQuery::class)->dashboard();
    }
}
