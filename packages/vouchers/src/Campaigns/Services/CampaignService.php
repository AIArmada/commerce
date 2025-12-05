<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Services;

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignEvent;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CampaignService
{
    /**
     * Create a new campaign.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Campaign
    {
        if (! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (! isset($data['status'])) {
            $data['status'] = CampaignStatus::Draft;
        }

        if (! isset($data['spent_cents'])) {
            $data['spent_cents'] = 0;
        }

        if (! isset($data['current_redemptions'])) {
            $data['current_redemptions'] = 0;
        }

        if (! isset($data['timezone'])) {
            $data['timezone'] = config('app.timezone', 'UTC');
        }

        /** @var Campaign $campaign */
        $campaign = Campaign::create($data);

        return $campaign;
    }

    /**
     * Update an existing campaign.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        if (! $campaign->status->canBeEdited() && $this->hasStructuralChanges($data)) {
            throw new InvalidArgumentException(
                "Campaign in {$campaign->status->label()} status cannot be structurally edited."
            );
        }

        $campaign->update($data);

        return $campaign->refresh();
    }

    /**
     * Delete a campaign.
     */
    public function delete(Campaign $campaign): bool
    {
        return $campaign->delete() ?? false;
    }

    /**
     * Activate a campaign.
     */
    public function activate(Campaign $campaign): Campaign
    {
        if (! $campaign->transitionTo(CampaignStatus::Active)) {
            throw new InvalidArgumentException(
                "Cannot activate campaign from {$campaign->status->label()} status."
            );
        }

        return $campaign->refresh();
    }

    /**
     * Pause a campaign.
     */
    public function pause(Campaign $campaign): Campaign
    {
        if (! $campaign->transitionTo(CampaignStatus::Paused)) {
            throw new InvalidArgumentException(
                "Cannot pause campaign from {$campaign->status->label()} status."
            );
        }

        return $campaign->refresh();
    }

    /**
     * Complete a campaign.
     */
    public function complete(Campaign $campaign): Campaign
    {
        if (! $campaign->transitionTo(CampaignStatus::Completed)) {
            throw new InvalidArgumentException(
                "Cannot complete campaign from {$campaign->status->label()} status."
            );
        }

        return $campaign->refresh();
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(Campaign $campaign): Campaign
    {
        if (! $campaign->transitionTo(CampaignStatus::Cancelled)) {
            throw new InvalidArgumentException(
                "Cannot cancel campaign from {$campaign->status->label()} status."
            );
        }

        return $campaign->refresh();
    }

    /**
     * Schedule a campaign for a future date.
     */
    public function schedule(Campaign $campaign, Carbon $startsAt, ?Carbon $endsAt = null): Campaign
    {
        if (! $campaign->status->canTransitionTo(CampaignStatus::Scheduled)) {
            throw new InvalidArgumentException(
                "Cannot schedule campaign from {$campaign->status->label()} status."
            );
        }

        $campaign->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => CampaignStatus::Scheduled,
        ]);

        return $campaign->refresh();
    }

    /**
     * Add a variant to a campaign.
     *
     * @param  array<string, mixed>  $data
     */
    public function addVariant(Campaign $campaign, array $data): CampaignVariant
    {
        if (! $campaign->ab_testing_enabled && $campaign->variants()->count() >= 1) {
            throw new InvalidArgumentException(
                'Cannot add more variants. A/B testing is not enabled for this campaign.'
            );
        }

        if (! isset($data['variant_code'])) {
            $existingCount = $campaign->variants()->count();
            $data['variant_code'] = chr(65 + $existingCount); // A, B, C, etc.
        }

        // Initialize metrics
        $data['impressions'] = 0;
        $data['applications'] = 0;
        $data['conversions'] = 0;
        $data['revenue_cents'] = 0;
        $data['discount_cents'] = 0;

        /** @var CampaignVariant $variant */
        $variant = $campaign->variants()->create($data);

        return $variant;
    }

    /**
     * Remove a variant from a campaign.
     */
    public function removeVariant(CampaignVariant $variant): bool
    {
        if ($variant->is_control) {
            throw new InvalidArgumentException('Cannot remove the control variant.');
        }

        if ($variant->conversions > 0) {
            throw new InvalidArgumentException(
                'Cannot remove a variant with existing conversions.'
            );
        }

        return $variant->delete() ?? false;
    }

    /**
     * Update traffic distribution across variants.
     *
     * @param  array<string, int>  $distribution  Map of variant_id => percentage
     */
    public function updateTrafficDistribution(Campaign $campaign, array $distribution): void
    {
        $total = array_sum($distribution);

        if ($total !== 100) {
            throw new InvalidArgumentException(
                "Traffic distribution must sum to 100%, got {$total}%."
            );
        }

        foreach ($distribution as $variantId => $percentage) {
            $campaign->variants()
                ->where('id', $variantId)
                ->update(['traffic_percentage' => $percentage]);
        }
    }

    /**
     * Assign a user to a variant for an A/B test.
     *
     * Uses consistent hashing based on user identifier for deterministic assignment.
     */
    public function assignVariant(Campaign $campaign, string $userIdentifier): ?CampaignVariant
    {
        if (! $campaign->ab_testing_enabled) {
            return $campaign->variants()->first();
        }

        if (! $campaign->canReceiveTraffic()) {
            return null;
        }

        // If winner is declared, always return winner
        if ($campaign->ab_winner_variant !== null) {
            return $campaign->getWinningVariant();
        }

        $variants = $campaign->variants()
            ->orderBy('variant_code')
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        // Consistent hashing for deterministic assignment
        $hash = crc32($campaign->id.$userIdentifier);
        $bucket = abs($hash) % 100;

        $cumulativePercentage = 0;
        foreach ($variants as $variant) {
            $cumulativePercentage += $variant->traffic_percentage;
            if ($bucket < $cumulativePercentage) {
                return $variant;
            }
        }

        // Fallback to last variant
        return $variants->last();
    }

    /**
     * Declare a winner for an A/B test.
     */
    public function declareWinner(Campaign $campaign, CampaignVariant $variant): Campaign
    {
        if (! $campaign->ab_testing_enabled) {
            throw new InvalidArgumentException('A/B testing is not enabled for this campaign.');
        }

        if ($variant->campaign_id !== $campaign->id) {
            throw new InvalidArgumentException('Variant does not belong to this campaign.');
        }

        $campaign->declareWinner($variant);

        // Update traffic to 100% for winner, 0% for others
        $campaign->variants()
            ->where('id', '!=', $variant->id)
            ->update(['traffic_percentage' => 0]);

        $variant->update(['traffic_percentage' => 100]);

        return $campaign->refresh();
    }

    /**
     * Auto-declare winner based on statistical significance.
     */
    public function autoSelectWinner(Campaign $campaign, float $minConfidence = 0.95): ?CampaignVariant
    {
        if (! $campaign->ab_testing_enabled) {
            return null;
        }

        $control = $campaign->getControlVariant();
        if ($control === null) {
            return null;
        }

        /** @var Collection<int, CampaignVariant> $treatments */
        $treatments = $campaign->variants()
            ->where('is_control', false)
            ->get();

        $bestVariant = null;
        $bestLift = 0.0;

        foreach ($treatments as $treatment) {
            $stats = $treatment->calculateSignificance($control);

            if ($stats === null) {
                continue;
            }

            // Check if significant and better than control
            $requiredPValue = 1 - $minConfidence;
            if ($stats['p_value'] <= $requiredPValue && $stats['z_score'] > 0) {
                $comparison = $treatment->compareToVariant($control);
                if ($comparison['conversion_lift'] > $bestLift) {
                    $bestLift = $comparison['conversion_lift'];
                    $bestVariant = $treatment;
                }
            }
        }

        // If no treatment beats control with significance, control wins
        if ($bestVariant === null) {
            // Check if we have enough data to declare control winner
            $hasEnoughData = $control->applications >= 100;
            $allTreatmentsTested = $treatments->every(fn (CampaignVariant $t): bool => $t->applications >= 100);

            if ($hasEnoughData && $allTreatmentsTested) {
                return $control;
            }
        }

        return $bestVariant;
    }

    /**
     * Attach a voucher to a campaign.
     */
    public function attachVoucher(Campaign $campaign, Voucher $voucher, ?CampaignVariant $variant = null): void
    {
        $voucher->update([
            'campaign_id' => $campaign->id,
            'campaign_variant_id' => $variant?->id,
        ]);

        if ($variant !== null && $variant->voucher_id === null) {
            $variant->update(['voucher_id' => $voucher->id]);
        }
    }

    /**
     * Detach a voucher from a campaign.
     */
    public function detachVoucher(Voucher $voucher): void
    {
        if ($voucher->campaign_id !== null) {
            $voucher->update([
                'campaign_id' => null,
                'campaign_variant_id' => null,
            ]);
        }
    }

    /**
     * Record an impression for a campaign.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordImpression(
        Campaign $campaign,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): CampaignEvent {
        return CampaignEvent::recordImpression($campaign, $variant, $attributes);
    }

    /**
     * Record a voucher application for a campaign.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordApplication(
        Campaign $campaign,
        string $voucherCode,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): CampaignEvent {
        return CampaignEvent::recordApplication($campaign, $voucherCode, $variant, $attributes);
    }

    /**
     * Record a conversion for a campaign.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordConversion(
        Campaign $campaign,
        string $voucherCode,
        int $valueCents,
        int $discountCents,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): CampaignEvent {
        // Record the event
        $event = CampaignEvent::recordConversion(
            $campaign,
            $voucherCode,
            $valueCents,
            $discountCents,
            $variant,
            $attributes
        );

        // Update campaign spending
        $campaign->recordSpending($discountCents);
        $campaign->recordRedemption();

        return $event;
    }

    /**
     * Get active campaigns that match the given criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return Collection<int, Campaign>
     */
    public function getActiveCampaigns(array $criteria = []): Collection
    {
        $query = Campaign::active();

        if (isset($criteria['type'])) {
            $query->ofType($criteria['type']);
        }

        if (isset($criteria['owner_type']) && isset($criteria['owner_id'])) {
            $query->where('owner_type', $criteria['owner_type'])
                ->where('owner_id', $criteria['owner_id']);
        }

        return $query->get();
    }

    /**
     * Get campaign statistics summary.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(Campaign $campaign): array
    {
        $events = $campaign->events();

        $impressions = $events->clone()->ofType(CampaignEventType::Impression)->count();
        $applications = $events->clone()->ofType(CampaignEventType::Application)->count();
        $conversions = $events->clone()->ofType(CampaignEventType::Conversion)->count();
        $abandonments = $events->clone()->ofType(CampaignEventType::Abandonment)->count();

        $revenue = $events->clone()
            ->ofType(CampaignEventType::Conversion)
            ->sum('value_cents');

        $discounts = $events->clone()
            ->ofType(CampaignEventType::Conversion)
            ->sum('discount_cents');

        return [
            'impressions' => $impressions,
            'applications' => $applications,
            'conversions' => $conversions,
            'abandonments' => $abandonments,
            'revenue_cents' => (int) $revenue,
            'discount_cents' => (int) $discounts,
            'net_revenue_cents' => (int) $revenue - (int) $discounts,
            'conversion_rate' => $applications > 0 ? ($conversions / $applications) * 100 : 0,
            'application_rate' => $impressions > 0 ? ($applications / $impressions) * 100 : 0,
            'abandonment_rate' => $applications > 0 ? ($abandonments / $applications) * 100 : 0,
            'budget_utilization' => $campaign->budget_utilization,
            'remaining_budget_cents' => $campaign->remaining_budget,
            'remaining_redemptions' => $campaign->remaining_redemptions,
        ];
    }

    /**
     * Check if data contains structural changes.
     *
     * @param  array<string, mixed>  $data
     */
    private function hasStructuralChanges(array $data): bool
    {
        $structuralFields = [
            'type',
            'objective',
            'budget_cents',
            'max_redemptions',
            'ab_testing_enabled',
        ];

        foreach ($structuralFields as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }
}
