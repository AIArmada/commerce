<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;

describe('CampaignEventType Enum', function (): void {
    describe('cases', function (): void {
        it('has Impression case', function (): void {
            expect(CampaignEventType::Impression->value)->toBe('impression');
        });

        it('has Application case', function (): void {
            expect(CampaignEventType::Application->value)->toBe('application');
        });

        it('has Conversion case', function (): void {
            expect(CampaignEventType::Conversion->value)->toBe('conversion');
        });

        it('has Abandonment case', function (): void {
            expect(CampaignEventType::Abandonment->value)->toBe('abandonment');
        });

        it('has Removal case', function (): void {
            expect(CampaignEventType::Removal->value)->toBe('removal');
        });

        it('has exactly 5 cases', function (): void {
            expect(CampaignEventType::cases())->toHaveCount(5);
        });
    });

    describe('options', function (): void {
        it('returns array of options for dropdowns', function (): void {
            $options = CampaignEventType::options();

            expect($options)->toBeArray()
                ->and($options)->toHaveKeys(['impression', 'application', 'conversion', 'abandonment', 'removal']);
        });

        it('maps values to labels', function (): void {
            $options = CampaignEventType::options();

            expect($options['impression'])->toBe('Impression')
                ->and($options['application'])->toBe('Applied')
                ->and($options['conversion'])->toBe('Converted')
                ->and($options['abandonment'])->toBe('Abandoned')
                ->and($options['removal'])->toBe('Removed');
        });
    });

    describe('label', function (): void {
        it('returns Impression label', function (): void {
            expect(CampaignEventType::Impression->label())->toBe('Impression');
        });

        it('returns Applied label for Application', function (): void {
            expect(CampaignEventType::Application->label())->toBe('Applied');
        });

        it('returns Converted label for Conversion', function (): void {
            expect(CampaignEventType::Conversion->label())->toBe('Converted');
        });

        it('returns Abandoned label for Abandonment', function (): void {
            expect(CampaignEventType::Abandonment->label())->toBe('Abandoned');
        });

        it('returns Removed label for Removal', function (): void {
            expect(CampaignEventType::Removal->label())->toBe('Removed');
        });
    });

    describe('description', function (): void {
        it('returns description for Impression', function (): void {
            expect(CampaignEventType::Impression->description())
                ->toBe('Voucher was displayed to user');
        });

        it('returns description for Application', function (): void {
            expect(CampaignEventType::Application->description())
                ->toBe('Voucher was applied to cart');
        });

        it('returns description for Conversion', function (): void {
            expect(CampaignEventType::Conversion->description())
                ->toBe('Order completed with voucher');
        });

        it('returns description for Abandonment', function (): void {
            expect(CampaignEventType::Abandonment->description())
                ->toBe('Cart abandoned with voucher applied');
        });

        it('returns description for Removal', function (): void {
            expect(CampaignEventType::Removal->description())
                ->toBe('Voucher was removed from cart');
        });
    });

    describe('incrementsMetric', function (): void {
        it('returns true for Impression', function (): void {
            expect(CampaignEventType::Impression->incrementsMetric())->toBeTrue();
        });

        it('returns true for Application', function (): void {
            expect(CampaignEventType::Application->incrementsMetric())->toBeTrue();
        });

        it('returns true for Conversion', function (): void {
            expect(CampaignEventType::Conversion->incrementsMetric())->toBeTrue();
        });

        it('returns false for Abandonment', function (): void {
            expect(CampaignEventType::Abandonment->incrementsMetric())->toBeFalse();
        });

        it('returns false for Removal', function (): void {
            expect(CampaignEventType::Removal->incrementsMetric())->toBeFalse();
        });
    });

    describe('variantMetric', function (): void {
        it('returns impressions for Impression', function (): void {
            expect(CampaignEventType::Impression->variantMetric())->toBe('impressions');
        });

        it('returns applications for Application', function (): void {
            expect(CampaignEventType::Application->variantMetric())->toBe('applications');
        });

        it('returns conversions for Conversion', function (): void {
            expect(CampaignEventType::Conversion->variantMetric())->toBe('conversions');
        });

        it('returns null for Abandonment', function (): void {
            expect(CampaignEventType::Abandonment->variantMetric())->toBeNull();
        });

        it('returns null for Removal', function (): void {
            expect(CampaignEventType::Removal->variantMetric())->toBeNull();
        });
    });
});
