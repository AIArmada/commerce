<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\SubscriptionStatus;
use AIArmada\Cashier\Support\UnifiedSubscription;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = UnifiedSubscriptionResource::class;

    protected ?string $activeGatewayFilter = null;

    protected ?SubscriptionStatus $activeStatusFilter = null;

    /**
     * @var Collection<int, UnifiedSubscription>|null
     */
    protected ?Collection $allSubscriptions = null;

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        return $table->recordAction(function ($record, Table $table): ?string {
            foreach (['view', 'edit'] as $action) {
                $action = $table->getAction($action);

                if (! $action) {
                    continue;
                }

                $action->record($record);
                $action->getGroup()?->record($record);

                if ($action->isHidden()) {
                    continue;
                }

                if ($action->getUrl()) {
                    continue;
                }

                return $action->getName();
            }

            return null;
        });
    }

    public function getTabs(): array
    {
        $detector = app(GatewayDetector::class);
        $gateways = $detector->availableGateways();

        $tabs = [
            'all' => Tab::make(__('filament-cashier::subscriptions.tabs.all'))
                ->badge(fn () => $this->getAllSubscriptions()->count()),
        ];

        foreach ($gateways as $gateway) {
            $tabs[$gateway] = Tab::make($detector->getLabel($gateway))
                ->badge(fn () => $this->getAllSubscriptions()->filter(fn (UnifiedSubscription $subscription): bool => $subscription->gateway === $gateway)->count())
                ->badgeColor($detector->getColor($gateway))
                ->icon($detector->getIcon($gateway));
        }

        $tabs['active'] = Tab::make(__('filament-cashier::subscriptions.tabs.active'))
            ->badge(fn () => $this->getAllSubscriptions()->filter(fn (UnifiedSubscription $sub) => $this->isActive($sub))->count())
            ->badgeColor('success');

        $tabs['issues'] = Tab::make(__('filament-cashier::subscriptions.tabs.issues'))
            ->badge(fn () => $this->getAllSubscriptions()->filter(fn (UnifiedSubscription $sub) => $this->isAttentionRequired($this->getStatus($sub)))->count())
            ->badgeColor('danger')
            ->icon('heroicon-o-exclamation-triangle');

        return $tabs;
    }

    /**
     * Override to use gateway-backed collection records instead of Eloquent.
     *
     * @return Collection<int, UnifiedSubscription>|Paginator|CursorPaginator
     */
    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        return $this->getFilteredSubscriptions();
    }

    /**
     * Get table record key.
     */
    public function getTableRecordKey(Model | array | UnifiedSubscription $record): string
    {
        if (is_array($record)) {
            return ($record['gateway'] ?? 'unknown') . '-' . ($record['id'] ?? 'unknown');
        }

        if ($record instanceof UnifiedSubscription) {
            return $record->gateway . '-' . $record->id;
        }

        return (string) $record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Get all subscriptions across all gateways through their gateway clients.
     *
     * @return Collection<int, UnifiedSubscription>
     */
    protected function getAllSubscriptions(): Collection
    {
        if ($this->allSubscriptions !== null) {
            return $this->allSubscriptions;
        }

        $user = auth()->user();

        if (! $user instanceof BillableContract || ! $user instanceof Model) {
            $this->allSubscriptions = collect();

            return $this->allSubscriptions;
        }

        $subscriptions = collect();
        $detector = app(GatewayDetector::class);

        foreach ($detector->availableGateways() as $gateway) {
            try {
                $gatewaySubscriptions = Cashier::gateway($gateway)->subscriptions($user);
            } catch (Throwable) {
                continue;
            }

            foreach ($gatewaySubscriptions->take(100) as $subscription) {
                if ($subscription instanceof SubscriptionContract) {
                    $subscriptions->push(UnifiedSubscription::fromGateway($subscription));
                }
            }
        }

        $this->allSubscriptions = $subscriptions->sortByDesc(fn (UnifiedSubscription $subscription): int => $subscription->createdAt->getTimestamp())->values();

        return $this->allSubscriptions;
    }

    /**
     * Filter subscriptions based on active tab and filters.
     *
     * @return Collection<int, UnifiedSubscription>
     */
    protected function getFilteredSubscriptions(): Collection
    {
        $subscriptions = $this->getAllSubscriptions();
        $activeTab = $this->activeTab;

        // Tab filtering
        if ($activeTab && ! in_array($activeTab, ['all', 'active', 'issues'])) {
            $subscriptions = $subscriptions->filter(fn (UnifiedSubscription $subscription): bool => $subscription->gateway === $activeTab);
        } elseif ($activeTab === 'active') {
            $subscriptions = $subscriptions->filter(fn (UnifiedSubscription $sub) => $this->isActive($sub));
        } elseif ($activeTab === 'issues') {
            $subscriptions = $subscriptions->filter(fn (UnifiedSubscription $sub) => $this->isAttentionRequired($this->getStatus($sub)));
        }

        // Apply filters from filter form
        $filterData = $this->tableFilters ?? [];

        if (isset($filterData['gateway']['value']) && $filterData['gateway']['value']) {
            $gateway = $filterData['gateway']['value'];
            $subscriptions = $subscriptions->filter(fn (UnifiedSubscription $subscription): bool => $subscription->gateway === $gateway);
        }

        if (isset($filterData['status']['value']) && $filterData['status']['value']) {
            $subscriptions = $subscriptions->filter(
                fn (UnifiedSubscription $sub) => $this->getStatus($sub)->value === $filterData['status']['value']
            );
        }

        return $subscriptions->values();
    }

    protected function isAttentionRequired(SubscriptionStatus $status): bool
    {
        return in_array($status, [SubscriptionStatus::PastDue, SubscriptionStatus::Incomplete], true);
    }

    protected function isActive(UnifiedSubscription $record): bool
    {
        return $this->getStatus($record)->isActive();
    }

    protected function getStatus(UnifiedSubscription $record): SubscriptionStatus
    {
        return $record->status;
    }
}
