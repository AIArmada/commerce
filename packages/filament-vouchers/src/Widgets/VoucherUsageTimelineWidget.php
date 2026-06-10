<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Support\AffiliateReportingContextResolver;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;

/**
 * Displays voucher usage history as a timeline
 * Uses existing voucher_usage table data - no database changes needed!
 */
#[Lazy]
final class VoucherUsageTimelineWidget extends Widget
{
    public ?Model $record = null;

    /** @var view-string */
    protected string $view = 'filament-vouchers::widgets.voucher-usage-timeline';

    protected int | string | array $columnSpan = 'full';

    /**
     * Get timeline events from voucher usage history
     *
     * @return Collection<int, array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     description: string,
     *     timestamp: CarbonImmutable,
     *     timestamp_human: string,
     *     icon: string,
     *     color: string,
     *     details: array<string, mixed>
     * }>
     */
    public function getTimelineEvents(): Collection
    {
        if (! $this->record instanceof Voucher) {
            return collect();
        }

        if (config('vouchers.owner.enabled', false)) {
            $voucherQuery = Voucher::query();

            $voucherQuery = OwnerQuery::applyToEloquentBuilder(
                $voucherQuery,
                OwnerContext::resolve(),
                (bool) config('vouchers.owner.include_global', false),
            );

            $isVisible = $voucherQuery
                ->whereKey($this->record->getKey())
                ->exists();

            if (! $isVisible) {
                return collect();
            }
        }

        $affiliateReporting = app(AffiliateReportingContextResolver::class);

        $usages = VoucherUsage::query()
            ->where('voucher_id', $this->record->id)
            ->with(['voucher', 'redeemedBy'])
            ->orderBy('used_at', 'desc')
            ->get();

        return $usages->map(
            fn (VoucherUsage $usage): array => $this->buildTimelineEvent($usage, $affiliateReporting)
        );
    }

    /**
     * Get summary statistics
     *
     * @return array{total_redemptions: int, total_savings: string, unique_customers: int}
     */
    public function getSummaryStats(): array
    {
        if (! $this->record instanceof Voucher) {
            return [
                'total_redemptions' => 0,
                'total_savings' => MoneyHelper::formatMoney(0, (string) config('filament-vouchers.default_currency', 'MYR')),
                'unique_customers' => 0,
            ];
        }

        if (config('vouchers.owner.enabled', false)) {
            $voucherQuery = Voucher::query();

            $voucherQuery = OwnerQuery::applyToEloquentBuilder(
                $voucherQuery,
                OwnerContext::resolve(),
                (bool) config('vouchers.owner.include_global', false),
            );

            $isVisible = $voucherQuery
                ->whereKey($this->record->getKey())
                ->exists();

            if (! $isVisible) {
                return [
                    'total_redemptions' => 0,
                    'total_savings' => MoneyHelper::formatMoney(0, (string) config('filament-vouchers.default_currency', 'MYR')),
                    'unique_customers' => 0,
                ];
            }
        }

        $usages = VoucherUsage::query()
            ->where('voucher_id', $this->record->id)
            ->get();

        $totalSavings = $usages->sum('discount_amount');
        $currency = $usages->first()?->currency ?? 'MYR';
        $uniqueCustomers = $usages->map(fn (VoucherUsage $u) => $u->user_identifier ?? $u->metadata['user_identifier'] ?? null)
            ->filter()
            ->unique()
            ->count();

        return [
            'total_redemptions' => $usages->count(),
            'total_savings' => MoneyHelper::formatMoney($totalSavings, (string) $currency),
            'unique_customers' => $uniqueCustomers,
        ];
    }

    /**
     * Build a timeline event from a usage record
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     description: string,
     *     timestamp: CarbonImmutable,
     *     timestamp_human: string,
     *     icon: string,
     *     color: string,
     *     details: array<string, mixed>
     * }
     */
    protected function buildTimelineEvent(VoucherUsage $usage, AffiliateReportingContextResolver $affiliateReporting): array
    {
        $savings = MoneyHelper::formatMoney($usage->discount_amount, (string) $usage->currency);

        $isManual = $usage->channel === VoucherUsage::CHANNEL_MANUAL;
        $hasOrder = $usage->isOrderRedemption();

        $title = $hasOrder
            ? 'Redeemed in Order'
            : ($isManual ? 'Manual Redemption' : 'Redeemed');

        $description = "Discount applied: {$savings}";
        $orderNumber = $affiliateReporting->orderNumber($usage);
        $orderId = $affiliateReporting->orderId($usage);

        if ($hasOrder && $orderNumber !== null) {
            $description .= " • Order: {$orderNumber}";
        } elseif ($usage->redeemedBy) {
            $description .= " • Customer: {$this->getCustomerName($usage)}";
        }

        $affiliateContext = $affiliateReporting->resolve($usage);
        $affiliateLabel = $affiliateReporting->affiliateLabel($affiliateContext);
        $sourceMediumLabel = $affiliateReporting->sourceMediumLabel($affiliateContext);
        $campaign = $affiliateContext['campaign'];

        if ($affiliateLabel !== null) {
            $description .= " • Affiliate: {$affiliateLabel}";
        }

        if ($sourceMediumLabel !== null) {
            $description .= " • Source: {$sourceMediumLabel}";
        }

        if ($campaign !== null) {
            $description .= " • Campaign: {$campaign}";
        }

        $details = [
            'savings' => $savings,
            'currency' => $usage->currency,
            'cart_identifier' => $usage->metadata['cart_identifier'] ?? $usage->cart_identifier ?? null,
            'user_identifier' => $usage->metadata['user_identifier'] ?? $usage->user_identifier ?? null,
            'channel' => $usage->channel,
            'notes' => $usage->notes,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'cart_snapshot' => $usage->metadata['cart_snapshot'] ?? null,
            'metadata' => $usage->metadata,
            'affiliate_code' => $affiliateContext['affiliate_code'],
            'affiliate_name' => $affiliateContext['affiliate_name'],
            'affiliate_source' => $affiliateContext['source'],
            'affiliate_medium' => $affiliateContext['medium'],
            'affiliate_campaign' => $campaign,
            'affiliate_label' => $affiliateLabel,
            'affiliate_source_medium' => $sourceMediumLabel,
        ];

        $cartSnapshot = $usage->metadata['cart_snapshot'] ?? null;

        if ($cartSnapshot !== null) {
            $details['cart_items_count'] = $cartSnapshot['items_count'] ?? null;
            $details['cart_total'] = $cartSnapshot['total'] ?? null;
        }

        if ($usage->metadata) {
            $details['subtotal'] = $usage->metadata['subtotal'] ?? null;
            $details['discount_total'] = $usage->metadata['discount_total'] ?? null;
            $details['grand_total'] = $usage->metadata['grand_total'] ?? null;
        }

        return [
            'id' => $usage->id,
            'type' => $hasOrder ? 'order_redemption' : ($isManual ? 'manual_redemption' : 'redemption'),
            'title' => $title,
            'description' => $description,
            'timestamp' => $usage->used_at,
            'timestamp_human' => $usage->used_at->diffForHumans(),
            'icon' => $this->getEventIcon($usage),
            'color' => $this->getEventColor($usage),
            'details' => $details,
        ];
    }

    /**
     * Get customer name from redeemed by relationship
     */
    protected function getCustomerName(VoucherUsage $usage): string
    {
        if (! $usage->redeemedBy) {
            return 'Guest';
        }

        // Try to get name from common attributes
        if (isset($usage->redeemedBy->name)) {
            return $usage->redeemedBy->name;
        }

        if (isset($usage->redeemedBy->email)) {
            return $usage->redeemedBy->email;
        }

        // Fallback to identifier
        return (string) ($usage->user_identifier ?? 'Guest');
    }

    /**
     * Get icon for event based on usage details
     */
    protected function getEventIcon(VoucherUsage $usage): string
    {
        if ($usage->isOrderRedemption()) {
            return 'heroicon-o-shopping-bag';
        }

        if ($usage->channel === VoucherUsage::CHANNEL_MANUAL) {
            return 'heroicon-o-hand-raised';
        }

        return 'heroicon-o-check-circle';
    }

    /**
     * Get color for event based on usage details
     */
    protected function getEventColor(VoucherUsage $usage): string
    {
        if ($usage->isOrderRedemption()) {
            return 'success';
        }

        if ($usage->channel === VoucherUsage::CHANNEL_MANUAL) {
            return 'info';
        }

        return 'primary';
    }
}
