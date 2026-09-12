<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $attemptsTable = (string) config(
            'cashier-chip.database.tables.renewal_attempts',
            'cashier_chip_renewal_attempts',
        );
        $subscriptionsTable = (string) config(
            'cashier-chip.database.tables.subscriptions',
            'cashier_chip_subscriptions',
        );

        if (! Schema::hasTable($attemptsTable)
            || ! Schema::hasTable($subscriptionsTable)
            || ! Schema::hasColumn($attemptsTable, 'owner_type')
            || ! Schema::hasColumn($attemptsTable, 'owner_id')
            || ! Schema::hasColumn($subscriptionsTable, 'owner_type')
            || ! Schema::hasColumn($subscriptionsTable, 'owner_id')) {
            return;
        }

        OwnerContext::withOwner(null, function () use ($attemptsTable, $subscriptionsTable): void {
            DB::table($attemptsTable)
                ->where(function (Builder $query): void {
                    $query->whereNull('owner_type')
                        ->orWhereNull('owner_id');
                })
                ->orderBy('id')
                ->chunkById(100, function (Collection $attempts) use ($attemptsTable, $subscriptionsTable): void {
                    $subscriptionIds = $attempts
                        ->pluck('subscription_id')
                        ->filter(fn (mixed $id): bool => is_string($id) || is_int($id))
                        ->map(fn (string | int $id): string => (string) $id)
                        ->unique()
                        ->values();

                    if ($subscriptionIds->isEmpty()) {
                        return;
                    }

                    $subscriptions = DB::table($subscriptionsTable)
                        ->whereIn('id', $subscriptionIds->all())
                        ->whereNotNull('owner_type')
                        ->whereNotNull('owner_id')
                        ->select(['id', 'owner_type', 'owner_id'])
                        ->get()
                        ->keyBy('id');

                    foreach ($attempts as $attempt) {
                        $subscription = $subscriptions->get($attempt->subscription_id);

                        if ($subscription === null) {
                            continue;
                        }

                        DB::table($attemptsTable)
                            ->where('id', $attempt->id)
                            ->where(function (Builder $query): void {
                                $query->whereNull('owner_type')
                                    ->orWhereNull('owner_id');
                            })
                            ->update([
                                'owner_type' => $subscription->owner_type,
                                'owner_id' => $subscription->owner_id,
                            ]);
                    }
                }, 'id');
        });
    }
};
