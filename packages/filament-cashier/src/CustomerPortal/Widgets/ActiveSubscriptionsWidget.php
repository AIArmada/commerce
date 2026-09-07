<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\FilamentCashier\Support\CustomerSubscriptionsQuery;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ActiveSubscriptionsWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.widgets.active-subscriptions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public int $perGatewayLimit = 5;

    /**
     * @return Collection<int, UnifiedSubscription>
     */
    public function getSubscriptions(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        if (! $user instanceof Model) {
            return collect();
        }

        $limit = max(1, $this->perGatewayLimit);
        $result = app(CustomerSubscriptionsQuery::class)->getForUser($user, $limit);

        return $result['items']->filter(fn (UnifiedSubscription $subscription): bool => $subscription->status->isActive());
    }
}
