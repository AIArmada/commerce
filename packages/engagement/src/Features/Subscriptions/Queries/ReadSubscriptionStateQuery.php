<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Queries;

use AIArmada\Engagement\Features\Subscriptions\Api\ReadsSubscriptionState;
use AIArmada\Engagement\Features\Subscriptions\Models\Subscription;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;

final readonly class ReadSubscriptionStateQuery implements ReadsSubscriptionState
{
    public function subscriptionsFor(mixed $subscriber, mixed $subject = null): array
    {
        $subscriber = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subscriber, 'subscriber'), 'subscriber');
        $subject = $subject === null ? null : EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        $query = Subscription::query()
            ->where('subscriber_type', $subscriber['type'])
            ->where('subscriber_id', $subscriber['id'])
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now()));

        if ($subject !== null) {
            $query
                ->where('subscribable_type', $subject['type'])
                ->where('subscribable_id', $subject['id']);
        }

        return $query->orderBy('id')->limit($this->resultLimit())->get()->toArray();
    }

    private function resultLimit(): int
    {
        return max(1, (int) config('engagement.state.result_limit', 100));
    }
}
