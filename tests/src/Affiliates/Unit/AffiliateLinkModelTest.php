<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Conversions\Api\LinkConversionReader;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

describe('AffiliateLink Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'LINK-TEST-' . uniqid(),
            'name' => 'Link Test Affiliate',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    it('can be created with required fields', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link)->toBeInstanceOf(AffiliateLink::class)
            ->and($link->destination_url)->toBe('https://example.com/product')
            ->and($link->tracking_url)->toBe('https://track.example.com/go/' . $this->affiliate->code);
    });

    it('references the affiliate through its identity directory', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect(app(AffiliateDirectory::class)->findById($link->affiliate_id)?->id)->toBe($this->affiliate->id)
            ->and(method_exists($link, 'affiliate'))->toBeFalse();
    });

    it('belongs to program', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'default_commission_rate' => 1000,
        ]);

        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $program->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link->program_id)->toBe($program->id);
    });

    it('rejects malformed and missing program references even without tenancy', function (): void {
        config(['affiliates.owner.enabled' => false]);

        foreach (['malformed', (string) Str::uuid()] as $programId) {
            expect(fn () => AffiliateLink::create([
                'affiliate_id' => $this->affiliate->id,
                'program_id' => $programId,
                'destination_url' => 'https://example.com/product',
                'tracking_url' => 'https://track.example.com/go/test',
            ]))->toThrow(AuthorizationException::class);
        }
    });

    it('blocks deletion when conversion history references the link through an attribution', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_link_id' => $link->id,
        ]);
        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_attribution_id' => $attribution->id,
            'value_minor' => 1000,
            'commission_minor' => 100,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        expect(app(LinkConversionReader::class)->hasLinkHistory($link->id, spl_object_id($link->getConnection())))->toBeTrue()
            ->and(fn () => app(TrackingLinkAdministration::class)->delete($link->id))->toThrow(ValidationException::class);
        expect(AffiliateLink::query()->find($link->id))->not->toBeNull();
    });

    it('increments clicks', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => 0,
        ]);

        app(TrackingLinkAdministration::class)->recordClick((string) $link->getKey());
        $link->refresh();

        expect($link->clicks)->toBe(1);

        app(TrackingLinkAdministration::class)->recordClick((string) $link->getKey());
        $link->refresh();

        expect($link->clicks)->toBe(2);
    });

    it('increments conversions', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'conversions' => 0,
        ]);

        app(TrackingLinkAdministration::class)->recordConversion((string) $link->getKey());
        $link->refresh();

        expect($link->conversions)->toBe(1);

        app(TrackingLinkAdministration::class)->recordConversion((string) $link->getKey());
        $link->refresh();

        expect($link->conversions)->toBe(2);
    });

    it('calculates conversion rate correctly', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => 100,
            'conversions' => 5,
        ]);

        expect($link->getConversionRate())->toBe(5.0);
    });

    it('returns zero conversion rate when no clicks', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => 0,
            'conversions' => 0,
        ]);

        expect($link->getConversionRate())->toBe(0.0);
    });

    it('returns short url as display url when available', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'short_url' => 'https://short.link/abc',
        ]);

        expect($link->getDisplayUrl())->toBe('https://short.link/abc');
    });

    it('returns tracking url as display url when no short url', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link->getDisplayUrl())->toBe('https://track.example.com/go/' . $this->affiliate->code);
    });

    it('supports custom slug', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'custom_slug' => 'my-custom-link',
        ]);

        expect($link->custom_slug)->toBe('my-custom-link');
    });

    it('supports campaign tracking', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'campaign' => 'summer_sale',
        ]);

        expect($link->campaign)->toBe('summer_sale');
    });

    it('supports sub IDs for tracking', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'sub_id' => 'facebook',
            'sub_id_2' => 'sidebar_ad',
            'sub_id_3' => 'variant_a',
        ]);

        expect($link->sub_id)->toBe('facebook')
            ->and($link->sub_id_2)->toBe('sidebar_ad')
            ->and($link->sub_id_3)->toBe('variant_a');
    });

    it('supports canonical share subject fields', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'subject_type' => 'product',
            'subject_key' => 'product:sku-123',
            'subject_id' => 'sku-123',
            'subject_instance' => 'web',
            'subject_title_snapshot' => 'SKU 123',
            'subject_metadata' => [
                'category' => 'featured',
            ],
        ]);

        expect($link->subject_type)->toBe('product')
            ->and($link->subject_key)->toBe('product:sku-123')
            ->and($link->subject_instance)->toBe('web')
            ->and($link->subject_title_snapshot)->toBe('SKU 123')
            ->and($link->subject_id)->toBe('sku-123')
            ->and(data_get($link->subject_metadata, 'subject_id'))->toBeNull();
    });

    it('has isEnabled true by default when deactivated_at is null', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link->isEnabled())->toBeTrue();
    });

    it('casts clicks and conversions as integers', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => '100',
            'conversions' => '5',
        ]);

        expect($link->clicks)->toBeInt()
            ->and($link->conversions)->toBeInt();
    });

    it('uses correct table name from config', function (): void {
        $link = new AffiliateLink;

        expect($link->getTable())->toBe('affiliate_links');
    });
});
