<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignEvent;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campaign = Campaign::create([
        'name' => 'Summer Sale',
        'type' => CampaignType::Promotional,
        'objective' => CampaignObjective::RevenueIncrease,
        'status' => CampaignStatus::Draft,
        'spent_cents' => 0,
        'current_redemptions' => 0,
        'timezone' => 'UTC',
        'ab_testing_enabled' => false,
    ]);
});

describe('Campaign Model', function (): void {
    it('can be created with required attributes', function (): void {
        expect($this->campaign)->toBeInstanceOf(Campaign::class)
            ->and($this->campaign->name)->toBe('Summer Sale')
            ->and($this->campaign->slug)->toBe('summer-sale')
            ->and($this->campaign->type)->toBe(CampaignType::Promotional)
            ->and($this->campaign->status)->toBe(CampaignStatus::Draft);
    });

    it('auto-generates slug from name', function (): void {
        $campaign = Campaign::create([
            'name' => 'Black Friday 2024 Mega Sale!',
            'type' => CampaignType::Flash,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Draft,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        expect($campaign->slug)->toBe('black-friday-2024-mega-sale');
    });

    it('can have variants relationship', function (): void {
        $variant = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Control',
            'variant_code' => 'A',
            'traffic_percentage' => 50,
            'is_control' => true,
            'impressions' => 0,
            'applications' => 0,
            'conversions' => 0,
            'revenue_cents' => 0,
            'discount_cents' => 0,
        ]);

        expect($this->campaign->variants)->toHaveCount(1)
            ->and($this->campaign->variants->first()->id)->toBe($variant->id);
    });

    it('can have events relationship', function (): void {
        $event = CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => \AIArmada\Vouchers\Campaigns\Enums\CampaignEventType::Impression,
            'occurred_at' => Carbon::now(),
        ]);

        expect($this->campaign->events)->toHaveCount(1)
            ->and($this->campaign->events->first()->id)->toBe($event->id);
    });
});

describe('Campaign Active State', function (): void {
    it('is active when status is active and within date range', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDay(),
        ]);

        expect($this->campaign->isActive())->toBeTrue();
    });

    it('is not active when status is not active', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Paused,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDay(),
        ]);

        expect($this->campaign->isActive())->toBeFalse();
    });

    it('is not active when before start date', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'starts_at' => Carbon::now()->addDay(),
        ]);

        expect($this->campaign->isActive())->toBeFalse();
    });

    it('is not active when after end date', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'starts_at' => Carbon::now()->subDays(2),
            'ends_at' => Carbon::now()->subDay(),
        ]);

        expect($this->campaign->isActive())->toBeFalse();
    });

    it('is active with null date range when status is active', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        expect($this->campaign->isActive())->toBeTrue();
    });
});

describe('Campaign Budget Management', function (): void {
    it('calculates remaining budget', function (): void {
        $this->campaign->update([
            'budget_cents' => 100000,
            'spent_cents' => 25000,
        ]);

        expect($this->campaign->remaining_budget)->toBe(75000);
    });

    it('returns null remaining budget when no budget set', function (): void {
        $this->campaign->update(['budget_cents' => null]);

        expect($this->campaign->remaining_budget)->toBeNull();
    });

    it('calculates budget utilization percentage', function (): void {
        $this->campaign->update([
            'budget_cents' => 100000,
            'spent_cents' => 50000,
        ]);

        expect($this->campaign->budget_utilization)->toBe(50.0);
    });

    it('has budget remaining when under limit', function (): void {
        $this->campaign->update([
            'budget_cents' => 100000,
            'spent_cents' => 50000,
        ]);

        expect($this->campaign->hasBudgetRemaining())->toBeTrue();
    });

    it('has no budget remaining when at limit', function (): void {
        $this->campaign->update([
            'budget_cents' => 100000,
            'spent_cents' => 100000,
        ]);

        expect($this->campaign->hasBudgetRemaining())->toBeFalse();
    });

    it('always has budget remaining when no budget set', function (): void {
        $this->campaign->update(['budget_cents' => null]);

        expect($this->campaign->hasBudgetRemaining())->toBeTrue();
    });

    it('can record spending', function (): void {
        $this->campaign->recordSpending(5000);

        expect($this->campaign->spent_cents)->toBe(5000);

        $this->campaign->recordSpending(3000);

        expect($this->campaign->fresh()->spent_cents)->toBe(8000);
    });
});

describe('Campaign Redemption Limits', function (): void {
    it('calculates remaining redemptions', function (): void {
        $this->campaign->update([
            'max_redemptions' => 1000,
            'current_redemptions' => 250,
        ]);

        expect($this->campaign->remaining_redemptions)->toBe(750);
    });

    it('returns null remaining redemptions when no limit set', function (): void {
        $this->campaign->update(['max_redemptions' => null]);

        expect($this->campaign->remaining_redemptions)->toBeNull();
    });

    it('has redemptions remaining when under limit', function (): void {
        $this->campaign->update([
            'max_redemptions' => 100,
            'current_redemptions' => 50,
        ]);

        expect($this->campaign->hasRedemptionsRemaining())->toBeTrue();
    });

    it('has no redemptions remaining when at limit', function (): void {
        $this->campaign->update([
            'max_redemptions' => 100,
            'current_redemptions' => 100,
        ]);

        expect($this->campaign->hasRedemptionsRemaining())->toBeFalse();
    });

    it('can record redemption', function (): void {
        $this->campaign->recordRedemption();

        expect($this->campaign->fresh()->current_redemptions)->toBe(1);
    });
});

describe('Campaign Status Transitions', function (): void {
    it('can transition from draft to active', function (): void {
        expect($this->campaign->transitionTo(CampaignStatus::Active))->toBeTrue()
            ->and($this->campaign->status)->toBe(CampaignStatus::Active);
    });

    it('cannot transition from draft to completed', function (): void {
        expect($this->campaign->transitionTo(CampaignStatus::Completed))->toBeFalse()
            ->and($this->campaign->status)->toBe(CampaignStatus::Draft);
    });

    it('can activate campaign', function (): void {
        expect($this->campaign->activate())->toBeTrue()
            ->and($this->campaign->status)->toBe(CampaignStatus::Active);
    });

    it('can pause active campaign', function (): void {
        $this->campaign->activate();

        expect($this->campaign->pause())->toBeTrue()
            ->and($this->campaign->status)->toBe(CampaignStatus::Paused);
    });

    it('can complete active campaign', function (): void {
        $this->campaign->activate();

        expect($this->campaign->complete())->toBeTrue()
            ->and($this->campaign->status)->toBe(CampaignStatus::Completed);
    });

    it('can cancel draft campaign', function (): void {
        expect($this->campaign->cancel())->toBeTrue()
            ->and($this->campaign->status)->toBe(CampaignStatus::Cancelled);
    });
});

describe('Campaign Traffic Eligibility', function (): void {
    it('can receive traffic when active and within limits', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'budget_cents' => 100000,
            'spent_cents' => 50000,
            'max_redemptions' => 100,
            'current_redemptions' => 50,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDay(),
        ]);

        expect($this->campaign->canReceiveTraffic())->toBeTrue();
    });

    it('cannot receive traffic when paused', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Paused,
            'budget_cents' => 100000,
            'spent_cents' => 0,
        ]);

        expect($this->campaign->canReceiveTraffic())->toBeFalse();
    });

    it('cannot receive traffic when budget depleted', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'budget_cents' => 100000,
            'spent_cents' => 100000,
        ]);

        expect($this->campaign->canReceiveTraffic())->toBeFalse();
    });

    it('cannot receive traffic when redemption limit reached', function (): void {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'max_redemptions' => 100,
            'current_redemptions' => 100,
        ]);

        expect($this->campaign->canReceiveTraffic())->toBeFalse();
    });
});

describe('Campaign A/B Testing', function (): void {
    beforeEach(function (): void {
        $this->campaign->update(['ab_testing_enabled' => true]);

        $this->controlVariant = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Control',
            'variant_code' => 'A',
            'traffic_percentage' => 50,
            'is_control' => true,
            'impressions' => 100,
            'applications' => 50,
            'conversions' => 10,
            'revenue_cents' => 100000,
            'discount_cents' => 10000,
        ]);

        $this->treatmentVariant = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Treatment',
            'variant_code' => 'B',
            'traffic_percentage' => 50,
            'is_control' => false,
            'impressions' => 100,
            'applications' => 50,
            'conversions' => 15,
            'revenue_cents' => 150000,
            'discount_cents' => 15000,
        ]);
    });

    it('can get control variant', function (): void {
        $control = $this->campaign->getControlVariant();

        expect($control)->not->toBeNull()
            ->and($control->variant_code)->toBe('A')
            ->and($control->is_control)->toBeTrue();
    });

    it('can declare a winner', function (): void {
        $this->campaign->declareWinner($this->treatmentVariant);

        expect($this->campaign->ab_winner_variant)->toBe('B')
            ->and($this->campaign->ab_winner_declared_at)->not->toBeNull();
    });

    it('can get winning variant after declaration', function (): void {
        $this->campaign->declareWinner($this->treatmentVariant);

        $winner = $this->campaign->getWinningVariant();

        expect($winner)->not->toBeNull()
            ->and($winner->id)->toBe($this->treatmentVariant->id);
    });

    it('returns null winning variant when no winner declared', function (): void {
        expect($this->campaign->getWinningVariant())->toBeNull();
    });
});

describe('Campaign Scopes', function (): void {
    it('can scope to active campaigns', function (): void {
        Campaign::create([
            'name' => 'Active Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDay(),
        ]);

        $activeCampaigns = Campaign::active()->get();

        expect($activeCampaigns)->toHaveCount(1)
            ->and($activeCampaigns->first()->name)->toBe('Active Campaign');
    });

    it('can scope by status', function (): void {
        Campaign::create([
            'name' => 'Paused Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Paused,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        $paused = Campaign::withStatus(CampaignStatus::Paused)->get();
        $drafts = Campaign::withStatus(CampaignStatus::Draft)->get();

        expect($paused)->toHaveCount(1)
            ->and($drafts)->toHaveCount(1);
    });

    it('can scope by type', function (): void {
        Campaign::create([
            'name' => 'Flash Sale',
            'type' => CampaignType::Flash,
            'objective' => CampaignObjective::InventoryClearance,
            'status' => CampaignStatus::Draft,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        $flash = Campaign::ofType(CampaignType::Flash)->get();
        $promotional = Campaign::ofType(CampaignType::Promotional)->get();

        expect($flash)->toHaveCount(1)
            ->and($promotional)->toHaveCount(1);
    });
});

describe('Campaign Cascade Delete', function (): void {
    it('deletes variants when campaign is deleted', function (): void {
        CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Control',
            'variant_code' => 'A',
            'traffic_percentage' => 100,
            'is_control' => true,
            'impressions' => 0,
            'applications' => 0,
            'conversions' => 0,
            'revenue_cents' => 0,
            'discount_cents' => 0,
        ]);

        expect(CampaignVariant::count())->toBe(1);

        $this->campaign->delete();

        expect(CampaignVariant::count())->toBe(0);
    });

    it('deletes events when campaign is deleted', function (): void {
        CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => \AIArmada\Vouchers\Campaigns\Enums\CampaignEventType::Impression,
            'occurred_at' => Carbon::now(),
        ]);

        expect(CampaignEvent::count())->toBe(1);

        $this->campaign->delete();

        expect(CampaignEvent::count())->toBe(0);
    });
});
