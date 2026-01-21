<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Models\UnifiedSubscriptionRecord;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\SubscriptionStatus;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Subscription;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = UnifiedSubscriptionResource::class;

    protected ?string $activeGatewayFilter = null;

    protected ?SubscriptionStatus $activeStatusFilter = null;

    /**
     * @var Collection<int, UnifiedSubscriptionRecord>|null
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
                ->badge(fn () => $this->getAllSubscriptions()->where('gateway', $gateway)->count())
                ->badgeColor($detector->getColor($gateway))
                ->icon($detector->getIcon($gateway));
        }

        $tabs['active'] = Tab::make(__('filament-cashier::subscriptions.tabs.active'))
            ->badge(fn () => $this->getAllSubscriptions()->filter(fn (UnifiedSubscriptionRecord $sub) => $this->isActive($sub))->count())
            ->badgeColor('success');

        $tabs['issues'] = Tab::make(__('filament-cashier::subscriptions.tabs.issues'))
            ->badge(fn () => $this->getAllSubscriptions()->filter(fn (UnifiedSubscriptionRecord $sub) => $this->isAttentionRequired($this->getStatus($sub)))->count())
            ->badgeColor('danger')
            ->icon('heroicon-o-exclamation-triangle');

        return $tabs;
    }

    /**
     * Override to use collection-based records instead of Eloquent.
     *
     * @return Collection<int, UnifiedSubscriptionRecord>|Paginator|CursorPaginator
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

        return (string) $record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Get all subscriptions across all gateways.
     *
     * @return Collection<int, UnifiedSubscriptionRecord>
     */
    protected function getAllSubscriptions(): Collection
    {
        if ($this->allSubscriptions !== null) {
            return $this->allSubscriptions;
        }

        $userId = auth()->id();

        if ($userId === null) {
            $this->allSubscriptions = collect();

            return $this->allSubscriptions;
        }

        $subscriptions = collect();
        $detector = app(GatewayDetector::class);

        // Collect from Stripe if available
        if (
            $detector->isAvailable('stripe')
            && class_exists(Subscription::class)
            && Schema::hasTable((new Subscription)->getTable())
        ) {
            $stripeSubscriptions = CashierOwnerScope::apply(Subscription::query())
                ->with(['user', 'items'])
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($sub) => $this->mapUnifiedSubscription(UnifiedSubscription::fromStripe($sub)));

            $subscriptions = $subscriptions->merge($stripeSubscriptions);
        }

        // Collect from CHIP if available
        if ($detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $chipSubscriptions = CashierOwnerScope::apply($subscriptionModel::query())
                ->with(['user', 'items'])
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($sub) => $this->mapUnifiedSubscription(UnifiedSubscription::fromChip($sub)));

            $subscriptions = $subscriptions->merge($chipSubscriptions);
        }

        $this->allSubscriptions = $subscriptions->sortByDesc('createdAt')->values();

        return $this->allSubscriptions;
    }

    /**
     * Filter subscriptions based on active tab and filters.
     *
     * @return Collection<int, UnifiedSubscriptionRecord>
     */
    protected function getFilteredSubscriptions(): Collection
    {
        $subscriptions = $this->getAllSubscriptions();
        $activeTab = $this->activeTab;

        // Tab filtering
        if ($activeTab && ! in_array($activeTab, ['all', 'active', 'issues'])) {
            $subscriptions = $subscriptions->where('gateway', $activeTab);
        } elseif ($activeTab === 'active') {
            $subscriptions = $subscriptions->filter(fn (UnifiedSubscriptionRecord $sub) => $this->isActive($sub));
        } elseif ($activeTab === 'issues') {
            $subscriptions = $subscriptions->filter(fn (UnifiedSubscriptionRecord $sub) => $this->isAttentionRequired($this->getStatus($sub)));
        }

        // Apply filters from filter form
        $filterData = $this->tableFilters ?? [];

        if (isset($filterData['gateway']['value']) && $filterData['gateway']['value']) {
            $subscriptions = $subscriptions->where('gateway', $filterData['gateway']['value']);
        }

        if (isset($filterData['status']['value']) && $filterData['status']['value']) {
            $subscriptions = $subscriptions->filter(
                fn (UnifiedSubscriptionRecord $sub) => $this->getStatus($sub)->value === $filterData['status']['value']
            );
        }

        return $subscriptions->values();
    }

    protected function mapUnifiedSubscription(UnifiedSubscription $subscription): UnifiedSubscriptionRecord
    {
        $record = new UnifiedSubscriptionRecord;
        $record->forceFill([
            'id' => $subscription->gateway . '-' . $subscription->id,
            'source_id' => $subscription->id,
            'gateway' => $subscription->gateway,
            'userId' => $subscription->userId,
            'type' => $subscription->type,
            'planId' => $subscription->planId,
            'amount' => $subscription->amount,
            'formatted_amount' => $subscription->formattedAmount(),
            'currency' => $subscription->currency,
            'quantity' => $subscription->quantity,
            'status' => $subscription->status,
            'trialEndsAt' => $subscription->trialEndsAt,
            'endsAt' => $subscription->endsAt,
            'nextBillingDate' => $subscription->nextBillingDate,
            'createdAt' => $subscription->createdAt,
            'gateway_config' => $subscription->gatewayConfig(),
            'external_dashboard_url' => $subscription->externalDashboardUrl(),
            'original' => $subscription->original,
        ]);

        return $record;
    }

    protected function isAttentionRequired(SubscriptionStatus $status): bool
    {
        return in_array($status, [SubscriptionStatus::PastDue, SubscriptionStatus::Incomplete], true);
    }

    protected function isActive(UnifiedSubscriptionRecord $record): bool
    {
        return $this->getStatus($record)->isActive();
    }

    protected function getStatus(UnifiedSubscriptionRecord $record): SubscriptionStatus
    {
        /** @var SubscriptionStatus $status */
        $status = $record->getAttribute('status');

        return $status;
    }
}
