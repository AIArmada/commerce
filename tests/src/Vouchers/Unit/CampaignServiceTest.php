<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignEvent;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Campaigns\Services\CampaignService;
use AIArmada\Vouchers\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new CampaignService;
});

describe('CampaignService CRUD Operations', function (): void {
    it('creates a campaign with required fields', function (): void {
        $campaign = $this->service->create([
            'name' => 'Black Friday Sale',
            'type' => CampaignType::Flash,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        expect($campaign)->toBeInstanceOf(Campaign::class)
            ->and($campaign->name)->toBe('Black Friday Sale')
            ->and($campaign->slug)->toBe('black-friday-sale')
            ->and($campaign->type)->toBe(CampaignType::Flash)
            ->and($campaign->status)->toBe(CampaignStatus::Draft)
            ->and($campaign->spent_cents)->toBe(0);
    });

    it('creates a campaign with all fields', function (): void {
        $campaign = $this->service->create([
            'name' => 'Summer Sale 2024',
            'description' => 'Biggest sale of the summer',
            'type' => CampaignType::Seasonal,
            'objective' => CampaignObjective::InventoryClearance,
            'budget_cents' => 1000000,
            'max_redemptions' => 5000,
            'starts_at' => Carbon::parse('2024-06-01'),
            'ends_at' => Carbon::parse('2024-08-31'),
            'ab_testing_enabled' => true,
        ]);

        expect($campaign->description)->toBe('Biggest sale of the summer')
            ->and($campaign->budget_cents)->toBe(1000000)
            ->and($campaign->max_redemptions)->toBe(5000)
            ->and($campaign->ab_testing_enabled)->toBeTrue();
    });

    it('updates a draft campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $updated = $this->service->update($campaign, [
            'name' => 'Updated Campaign',
            'budget_cents' => 500000,
        ]);

        expect($updated->name)->toBe('Updated Campaign')
            ->and($updated->budget_cents)->toBe(500000);
    });

    it('throws when updating structural fields on active campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $this->service->activate($campaign);

        $this->service->update($campaign, [
            'budget_cents' => 500000,
        ]);
    })->throws(InvalidArgumentException::class);

    it('allows updating non-structural fields on active campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $this->service->activate($campaign);

        $updated = $this->service->update($campaign, [
            'description' => 'Updated description',
        ]);

        expect($updated->description)->toBe('Updated description');
    });

    it('deletes a campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        expect($this->service->delete($campaign))->toBeTrue()
            ->and(Campaign::find($campaign->id))->toBeNull();
    });
});

describe('CampaignService Status Transitions', function (): void {
    it('activates a draft campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $activated = $this->service->activate($campaign);

        expect($activated->status)->toBe(CampaignStatus::Active);
    });

    it('throws when activating completed campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $this->service->activate($campaign);
        $this->service->complete($campaign);

        $this->service->activate($campaign);
    })->throws(InvalidArgumentException::class);

    it('pauses an active campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $this->service->activate($campaign);
        $paused = $this->service->pause($campaign);

        expect($paused->status)->toBe(CampaignStatus::Paused);
    });

    it('completes an active campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $this->service->activate($campaign);
        $completed = $this->service->complete($campaign);

        expect($completed->status)->toBe(CampaignStatus::Completed);
    });

    it('cancels a campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $cancelled = $this->service->cancel($campaign);

        expect($cancelled->status)->toBe(CampaignStatus::Cancelled);
    });

    it('schedules a campaign for future date', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $startsAt = Carbon::now()->addDays(7);
        $endsAt = Carbon::now()->addDays(14);

        $scheduled = $this->service->schedule($campaign, $startsAt, $endsAt);

        expect($scheduled->status)->toBe(CampaignStatus::Scheduled)
            ->and($scheduled->starts_at->toDateString())->toBe($startsAt->toDateString())
            ->and($scheduled->ends_at->toDateString())->toBe($endsAt->toDateString());
    });
});

describe('CampaignService Variant Management', function (): void {
    it('adds a variant to a campaign with A/B testing enabled', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $variant = $this->service->addVariant($campaign, [
            'name' => 'Control',
            'traffic_percentage' => 50,
            'is_control' => true,
        ]);

        expect($variant)->toBeInstanceOf(CampaignVariant::class)
            ->and($variant->variant_code)->toBe('A')
            ->and($variant->impressions)->toBe(0);
    });

    it('auto-generates variant codes', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $variantA = $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 34, 'is_control' => true]);
        $variantB = $this->service->addVariant($campaign, ['name' => 'B', 'traffic_percentage' => 33, 'is_control' => false]);
        $variantC = $this->service->addVariant($campaign, ['name' => 'C', 'traffic_percentage' => 33, 'is_control' => false]);

        expect($variantA->variant_code)->toBe('A')
            ->and($variantB->variant_code)->toBe('B')
            ->and($variantC->variant_code)->toBe('C');
    });

    it('throws when adding multiple variants without A/B testing', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => false,
        ]);

        $this->service->addVariant($campaign, ['name' => 'Single', 'traffic_percentage' => 100, 'is_control' => true]);
        $this->service->addVariant($campaign, ['name' => 'Another', 'traffic_percentage' => 0, 'is_control' => false]);
    })->throws(InvalidArgumentException::class);

    it('removes a treatment variant', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $this->service->addVariant($campaign, ['name' => 'Control', 'traffic_percentage' => 50, 'is_control' => true]);
        $treatment = $this->service->addVariant($campaign, ['name' => 'Treatment', 'traffic_percentage' => 50, 'is_control' => false]);

        expect($this->service->removeVariant($treatment))->toBeTrue();
    });

    it('throws when removing control variant', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $control = $this->service->addVariant($campaign, ['name' => 'Control', 'traffic_percentage' => 100, 'is_control' => true]);

        $this->service->removeVariant($control);
    })->throws(InvalidArgumentException::class);

    it('updates traffic distribution', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $variantA = $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 50, 'is_control' => true]);
        $variantB = $this->service->addVariant($campaign, ['name' => 'B', 'traffic_percentage' => 50, 'is_control' => false]);

        $this->service->updateTrafficDistribution($campaign, [
            $variantA->id => 70,
            $variantB->id => 30,
        ]);

        expect($variantA->fresh()->traffic_percentage)->toBe(70)
            ->and($variantB->fresh()->traffic_percentage)->toBe(30);
    });

    it('throws when traffic distribution does not sum to 100', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $variantA = $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 50, 'is_control' => true]);
        $variantB = $this->service->addVariant($campaign, ['name' => 'B', 'traffic_percentage' => 50, 'is_control' => false]);

        $this->service->updateTrafficDistribution($campaign, [
            $variantA->id => 60,
            $variantB->id => 30,
        ]);
    })->throws(InvalidArgumentException::class);
});

describe('CampaignService A/B Testing', function (): void {
    it('assigns a variant consistently for same user', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'ab_testing_enabled' => true,
        ]);

        $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 50, 'is_control' => true]);
        $this->service->addVariant($campaign, ['name' => 'B', 'traffic_percentage' => 50, 'is_control' => false]);

        $userIdentifier = 'user-123';

        $assigned1 = $this->service->assignVariant($campaign, $userIdentifier);
        $assigned2 = $this->service->assignVariant($campaign, $userIdentifier);

        expect($assigned1->id)->toBe($assigned2->id);
    });

    it('returns first variant when A/B testing disabled', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'ab_testing_enabled' => false,
        ]);

        $variant = $this->service->addVariant($campaign, ['name' => 'Single', 'traffic_percentage' => 100, 'is_control' => true]);

        $assigned = $this->service->assignVariant($campaign, 'user-123');

        expect($assigned->id)->toBe($variant->id);
    });

    it('returns null when campaign cannot receive traffic', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Paused,
            'ab_testing_enabled' => true,
        ]);

        $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 100, 'is_control' => true]);

        $assigned = $this->service->assignVariant($campaign, 'user-123');

        expect($assigned)->toBeNull();
    });

    it('returns winner variant after winner declared', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'ab_testing_enabled' => true,
        ]);

        $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 50, 'is_control' => true]);
        $winner = $this->service->addVariant($campaign, ['name' => 'B', 'traffic_percentage' => 50, 'is_control' => false]);

        $this->service->declareWinner($campaign, $winner);

        // All users should now get the winner
        $assigned1 = $this->service->assignVariant($campaign->fresh(), 'user-123');
        $assigned2 = $this->service->assignVariant($campaign->fresh(), 'user-456');

        expect($assigned1->id)->toBe($winner->id)
            ->and($assigned2->id)->toBe($winner->id);
    });

    it('declares a winner and updates traffic', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'ab_testing_enabled' => true,
        ]);

        $control = $this->service->addVariant($campaign, ['name' => 'A', 'traffic_percentage' => 50, 'is_control' => true]);
        $treatment = $this->service->addVariant($campaign, ['name' => 'B', 'traffic_percentage' => 50, 'is_control' => false]);

        $updated = $this->service->declareWinner($campaign, $treatment);

        expect($updated->ab_winner_variant)->toBe('B')
            ->and($control->fresh()->traffic_percentage)->toBe(0)
            ->and($treatment->fresh()->traffic_percentage)->toBe(100);
    });

    it('throws when declaring winner for non-A/B campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => false,
        ]);

        $variant = $this->service->addVariant($campaign, ['name' => 'Single', 'traffic_percentage' => 100, 'is_control' => true]);

        $this->service->declareWinner($campaign, $variant);
    })->throws(InvalidArgumentException::class);
});

describe('CampaignService Voucher Integration', function (): void {
    it('attaches a voucher to a campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $voucher = Voucher::create([
            'code' => 'SUMMER10',
            'name' => 'Summer Discount',
            'type' => AIArmada\Vouchers\Enums\VoucherType::Percentage,
            'value' => 1000,
            'status' => AIArmada\Vouchers\Enums\VoucherStatus::Active,
        ]);

        $this->service->attachVoucher($campaign, $voucher);

        expect($voucher->fresh()->campaign_id)->toBe($campaign->id);
    });

    it('attaches a voucher to a campaign variant', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'ab_testing_enabled' => true,
        ]);

        $variant = $this->service->addVariant($campaign, ['name' => 'Control', 'traffic_percentage' => 100, 'is_control' => true]);

        $voucher = Voucher::create([
            'code' => 'SUMMER10',
            'name' => 'Summer Discount',
            'type' => AIArmada\Vouchers\Enums\VoucherType::Percentage,
            'value' => 1000,
            'status' => AIArmada\Vouchers\Enums\VoucherStatus::Active,
        ]);

        $this->service->attachVoucher($campaign, $voucher, $variant);

        expect($voucher->fresh()->campaign_id)->toBe($campaign->id)
            ->and($voucher->fresh()->campaign_variant_id)->toBe($variant->id)
            ->and($variant->fresh()->voucher_id)->toBe($voucher->id);
    });

    it('detaches a voucher from a campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $voucher = Voucher::create([
            'code' => 'SUMMER10',
            'name' => 'Summer Discount',
            'type' => AIArmada\Vouchers\Enums\VoucherType::Percentage,
            'value' => 1000,
            'status' => AIArmada\Vouchers\Enums\VoucherStatus::Active,
        ]);

        $this->service->attachVoucher($campaign, $voucher);
        $this->service->detachVoucher($voucher);

        expect($voucher->fresh()->campaign_id)->toBeNull();
    });
});

describe('CampaignService Event Recording', function (): void {
    it('records an impression event', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $event = $this->service->recordImpression($campaign);

        expect($event)->toBeInstanceOf(CampaignEvent::class)
            ->and($event->event_type)->toBe(CampaignEventType::Impression)
            ->and($event->campaign_id)->toBe($campaign->id);
    });

    it('records an application event', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $event = $this->service->recordApplication($campaign, 'SUMMER10');

        expect($event->event_type)->toBe(CampaignEventType::Application)
            ->and($event->voucher_code)->toBe('SUMMER10');
    });

    it('records a conversion event and updates campaign', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'budget_cents' => 100000,
        ]);

        $event = $this->service->recordConversion($campaign, 'SUMMER10', 50000, 5000);

        expect($event->event_type)->toBe(CampaignEventType::Conversion)
            ->and($event->value_cents)->toBe(50000)
            ->and($event->discount_cents)->toBe(5000)
            ->and($campaign->fresh()->spent_cents)->toBe(5000)
            ->and($campaign->fresh()->current_redemptions)->toBe(1);
    });
});

describe('CampaignService Statistics', function (): void {
    it('returns campaign statistics', function (): void {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'budget_cents' => 100000,
        ]);

        // Record some events
        $this->service->recordImpression($campaign);
        $this->service->recordImpression($campaign);
        $this->service->recordApplication($campaign, 'CODE1');
        $this->service->recordConversion($campaign, 'CODE1', 50000, 5000);

        $stats = $this->service->getStatistics($campaign);

        expect($stats)->toHaveKeys([
            'impressions',
            'applications',
            'conversions',
            'revenue_cents',
            'discount_cents',
            'conversion_rate',
            'application_rate',
        ])
            ->and($stats['impressions'])->toBe(2)
            ->and($stats['applications'])->toBe(1)
            ->and($stats['conversions'])->toBe(1)
            ->and($stats['revenue_cents'])->toBe(50000)
            ->and($stats['discount_cents'])->toBe(5000);
    });

    it('returns active campaigns', function (): void {
        $this->service->create([
            'name' => 'Draft Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
        ]);

        $active = $this->service->create([
            'name' => 'Active Campaign',
            'type' => CampaignType::Flash,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
        ]);

        $activeCampaigns = $this->service->getActiveCampaigns();

        expect($activeCampaigns)->toHaveCount(1)
            ->and($activeCampaigns->first()->id)->toBe($active->id);
    });

    it('filters active campaigns by type', function (): void {
        $this->service->create([
            'name' => 'Active Promotional',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
        ]);

        $this->service->create([
            'name' => 'Active Flash',
            'type' => CampaignType::Flash,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
        ]);

        $flashCampaigns = $this->service->getActiveCampaigns(['type' => CampaignType::Flash]);

        expect($flashCampaigns)->toHaveCount(1)
            ->and($flashCampaigns->first()->type)->toBe(CampaignType::Flash);
    });
});
