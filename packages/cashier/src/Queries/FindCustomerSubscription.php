<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;

final class FindCustomerSubscription
{
    public function handle(Model $user, string $gateway, string $id): ?UnifiedSubscription
    {
        if (! $user->exists || $user->getKey() === null || mb_trim($id) === '' || ! app(GatewayDetector::class)->isAvailable($gateway)) {
            return null;
        }

        if ($user instanceof BillableContract) {
            ActionGuard::assertBillableInScope($user);
        }

        if ($gateway === 'stripe' && class_exists(Subscription::class)) {
            $model = new Subscription;

            if (! $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                return null;
            }

            $subscription = OwnerScopedQuery::apply($model->newQuery())
                ->with('items')
                ->where('user_id', $user->getKey())
                ->whereKey($id)
                ->first();

            return $subscription === null ? null : UnifiedSubscription::fromStripe($subscription);
        }

        if ($gateway === 'chip' && class_exists(CashierChip::class)) {
            $model = CashierChip::$subscriptionModel;
            $subscription = OwnerScopedQuery::apply($model::query())
                ->with(['billable', 'items'])
                ->where('billable_type', $user->getMorphClass())
                ->where('billable_id', (string) $user->getKey())
                ->whereKey($id)
                ->first();

            return $subscription === null ? null : UnifiedSubscription::fromChip($subscription);
        }

        return null;
    }
}
