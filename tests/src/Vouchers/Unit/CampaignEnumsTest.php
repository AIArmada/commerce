<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;

describe('CampaignType Enum', function (): void {
    it('has correct labels for all types', function (): void {
        expect(CampaignType::Promotional->label())->toBe('Promotional')
            ->and(CampaignType::Acquisition->label())->toBe('Customer Acquisition')
            ->and(CampaignType::Retention->label())->toBe('Customer Retention')
            ->and(CampaignType::Loyalty->label())->toBe('Loyalty Program')
            ->and(CampaignType::Seasonal->label())->toBe('Seasonal')
            ->and(CampaignType::Flash->label())->toBe('Flash Sale')
            ->and(CampaignType::Referral->label())->toBe('Referral Program');
    });

    it('has descriptions for all types', function (): void {
        expect(CampaignType::Promotional->description())->toBeString()
            ->and(CampaignType::Flash->description())->toContain('Limited-time');
    });

    it('provides options for form selects', function (): void {
        $options = CampaignType::options();

        expect($options)->toBeArray()
            ->and($options)->toHaveCount(7)
            ->and($options['promotional'])->toBe('Promotional')
            ->and($options['flash'])->toBe('Flash Sale');
    });
});

describe('CampaignObjective Enum', function (): void {
    it('has correct labels for all objectives', function (): void {
        expect(CampaignObjective::RevenueIncrease->label())->toBe('Revenue Increase')
            ->and(CampaignObjective::AverageOrderValue->label())->toBe('Average Order Value Increase')
            ->and(CampaignObjective::NewCustomerAcquisition->label())->toBe('New Customer Acquisition')
            ->and(CampaignObjective::InventoryClearance->label())->toBe('Inventory Clearance');
    });

    it('has primary metrics for tracking', function (): void {
        expect(CampaignObjective::RevenueIncrease->primaryMetric())->toBe('revenue')
            ->and(CampaignObjective::OrderVolumeIncrease->primaryMetric())->toBe('conversions')
            ->and(CampaignObjective::AverageOrderValue->primaryMetric())->toBe('aov')
            ->and(CampaignObjective::NewCustomerAcquisition->primaryMetric())->toBe('new_customers')
            ->and(CampaignObjective::CustomerRetention->primaryMetric())->toBe('returning_customers')
            ->and(CampaignObjective::InventoryClearance->primaryMetric())->toBe('units_sold')
            ->and(CampaignObjective::CategoryGrowth->primaryMetric())->toBe('category_revenue')
            ->and(CampaignObjective::BrandAwareness->primaryMetric())->toBe('impressions');
    });

    it('provides options for form selects', function (): void {
        $options = CampaignObjective::options();

        expect($options)->toBeArray()
            ->and($options)->toHaveCount(8);
    });
});

describe('CampaignStatus Enum', function (): void {
    it('has correct labels for all statuses', function (): void {
        expect(CampaignStatus::Draft->label())->toBe('Draft')
            ->and(CampaignStatus::Scheduled->label())->toBe('Scheduled')
            ->and(CampaignStatus::Active->label())->toBe('Active')
            ->and(CampaignStatus::Paused->label())->toBe('Paused')
            ->and(CampaignStatus::Completed->label())->toBe('Completed')
            ->and(CampaignStatus::Cancelled->label())->toBe('Cancelled');
    });

    it('has colors for UI display', function (): void {
        expect(CampaignStatus::Draft->color())->toBe('gray')
            ->and(CampaignStatus::Active->color())->toBe('success')
            ->and(CampaignStatus::Paused->color())->toBe('warning')
            ->and(CampaignStatus::Cancelled->color())->toBe('danger');
    });

    it('correctly identifies traffic-receiving statuses', function (): void {
        expect(CampaignStatus::Active->canReceiveTraffic())->toBeTrue()
            ->and(CampaignStatus::Draft->canReceiveTraffic())->toBeFalse()
            ->and(CampaignStatus::Paused->canReceiveTraffic())->toBeFalse()
            ->and(CampaignStatus::Completed->canReceiveTraffic())->toBeFalse();
    });

    it('correctly identifies editable statuses', function (): void {
        expect(CampaignStatus::Draft->canBeEdited())->toBeTrue()
            ->and(CampaignStatus::Scheduled->canBeEdited())->toBeTrue()
            ->and(CampaignStatus::Paused->canBeEdited())->toBeTrue()
            ->and(CampaignStatus::Active->canBeEdited())->toBeFalse()
            ->and(CampaignStatus::Completed->canBeEdited())->toBeFalse();
    });

    it('correctly identifies terminal statuses', function (): void {
        expect(CampaignStatus::Completed->isTerminal())->toBeTrue()
            ->and(CampaignStatus::Cancelled->isTerminal())->toBeTrue()
            ->and(CampaignStatus::Active->isTerminal())->toBeFalse()
            ->and(CampaignStatus::Draft->isTerminal())->toBeFalse();
    });

    it('returns correct allowed transitions for each status', function (): void {
        expect(CampaignStatus::Draft->allowedTransitions())
            ->toContain(CampaignStatus::Scheduled, CampaignStatus::Active, CampaignStatus::Cancelled);

        expect(CampaignStatus::Active->allowedTransitions())
            ->toContain(CampaignStatus::Paused, CampaignStatus::Completed, CampaignStatus::Cancelled);

        expect(CampaignStatus::Completed->allowedTransitions())->toBeEmpty();
        expect(CampaignStatus::Cancelled->allowedTransitions())->toBeEmpty();
    });

    it('can check transition validity', function (): void {
        expect(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Active))->toBeTrue()
            ->and(CampaignStatus::Draft->canTransitionTo(CampaignStatus::Completed))->toBeFalse()
            ->and(CampaignStatus::Active->canTransitionTo(CampaignStatus::Paused))->toBeTrue()
            ->and(CampaignStatus::Paused->canTransitionTo(CampaignStatus::Active))->toBeTrue()
            ->and(CampaignStatus::Completed->canTransitionTo(CampaignStatus::Active))->toBeFalse();
    });
});

describe('CampaignEventType Enum', function (): void {
    it('has correct labels for all event types', function (): void {
        expect(CampaignEventType::Impression->label())->toBe('Impression')
            ->and(CampaignEventType::Application->label())->toBe('Applied')
            ->and(CampaignEventType::Conversion->label())->toBe('Converted')
            ->and(CampaignEventType::Abandonment->label())->toBe('Abandoned')
            ->and(CampaignEventType::Removal->label())->toBe('Removed');
    });

    it('has descriptions for all event types', function (): void {
        expect(CampaignEventType::Impression->description())->toContain('displayed')
            ->and(CampaignEventType::Conversion->description())->toContain('completed');
    });

    it('correctly identifies metric-incrementing events', function (): void {
        expect(CampaignEventType::Impression->incrementsMetric())->toBeTrue()
            ->and(CampaignEventType::Application->incrementsMetric())->toBeTrue()
            ->and(CampaignEventType::Conversion->incrementsMetric())->toBeTrue()
            ->and(CampaignEventType::Abandonment->incrementsMetric())->toBeFalse()
            ->and(CampaignEventType::Removal->incrementsMetric())->toBeFalse();
    });

    it('returns correct variant metric fields', function (): void {
        expect(CampaignEventType::Impression->variantMetric())->toBe('impressions')
            ->and(CampaignEventType::Application->variantMetric())->toBe('applications')
            ->and(CampaignEventType::Conversion->variantMetric())->toBe('conversions')
            ->and(CampaignEventType::Abandonment->variantMetric())->toBeNull()
            ->and(CampaignEventType::Removal->variantMetric())->toBeNull();
    });
});
