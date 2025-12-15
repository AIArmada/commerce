<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignEvent;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use Carbon\Carbon;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->campaign = Campaign::create([
        'name' => 'Test Campaign',
        'slug' => 'test-campaign-' . uniqid(),
        'status' => 'active',
    ]);
});

describe('CampaignEvent model', function (): void {
    describe('recordImpression()', function (): void {
        it('records an impression event', function (): void {
            $event = CampaignEvent::recordImpression($this->campaign);

            expect($event)->toBeInstanceOf(CampaignEvent::class);
            expect($event->event_type)->toBe(CampaignEventType::Impression);
            expect($event->campaign_id)->toBe($this->campaign->id);
            expect($event->occurred_at)->not->toBeNull();
        });

        it('records impression with variant', function (): void {
            $variant = CampaignVariant::create([
                'campaign_id' => $this->campaign->id,
                'name' => 'Test Variant',
                'variant_code' => 'TEST-V-' . uniqid(),
                'slug' => 'test-variant',
                'weight' => 100,
            ]);

            $event = CampaignEvent::recordImpression($this->campaign, $variant);

            expect($event->variant_id)->toBe($variant->id);
        });

        it('records impression with custom attributes', function (): void {
            $event = CampaignEvent::recordImpression($this->campaign, null, [
                'channel' => 'web',
                'source' => 'homepage',
            ]);

            expect($event->channel)->toBe('web');
            expect($event->source)->toBe('homepage');
        });
    });

    describe('recordApplication()', function (): void {
        it('records an application event', function (): void {
            $event = CampaignEvent::recordApplication($this->campaign, 'TEST123');

            expect($event->event_type)->toBe(CampaignEventType::Application);
            expect($event->voucher_code)->toBe('TEST123');
        });

        it('records application with variant', function (): void {
            $variant = CampaignVariant::create([
                'campaign_id' => $this->campaign->id,
                'name' => 'App Variant',
                'variant_code' => 'APP-V-' . uniqid(),
                'slug' => 'app-variant',
                'weight' => 100,
            ]);

            $event = CampaignEvent::recordApplication($this->campaign, 'CODE1', $variant);

            expect($event->variant_id)->toBe($variant->id);
        });
    });

    describe('recordConversion()', function (): void {
        it('records a conversion event', function (): void {
            $event = CampaignEvent::recordConversion(
                $this->campaign,
                'CONVERT123',
                50000,
                5000
            );

            expect($event->event_type)->toBe(CampaignEventType::Conversion);
            expect($event->voucher_code)->toBe('CONVERT123');
            expect($event->value_cents)->toBe(50000);
            expect($event->discount_cents)->toBe(5000);
        });

        it('increments variant metrics on conversion', function (): void {
            $variant = CampaignVariant::create([
                'campaign_id' => $this->campaign->id,
                'name' => 'Conv Variant',
                'variant_code' => 'CONV-V-' . uniqid(),
                'slug' => 'conv-variant',
                'weight' => 100,
                'impressions' => 0,
                'applications' => 0,
                'conversions' => 0,
                'revenue_cents' => 0,
                'discount_cents' => 0,
            ]);

            CampaignEvent::recordConversion(
                $this->campaign,
                'CONV1',
                10000,
                1000,
                $variant
            );

            $variant->refresh();

            expect($variant->conversions)->toBe(1);
            expect($variant->revenue_cents)->toBe(10000);
            expect($variant->discount_cents)->toBe(1000);
        });
    });

    describe('recordAbandonment()', function (): void {
        it('records an abandonment event', function (): void {
            $event = CampaignEvent::recordAbandonment($this->campaign, 'ABANDON1');

            expect($event->event_type)->toBe(CampaignEventType::Abandonment);
            expect($event->voucher_code)->toBe('ABANDON1');
        });
    });

    describe('recordRemoval()', function (): void {
        it('records a removal event', function (): void {
            $event = CampaignEvent::recordRemoval($this->campaign, 'REMOVE1');

            expect($event->event_type)->toBe(CampaignEventType::Removal);
            expect($event->voucher_code)->toBe('REMOVE1');
        });
    });

    describe('recordEvent()', function (): void {
        it('records event with all attributes', function (): void {
            $event = CampaignEvent::recordEvent(
                CampaignEventType::Application,
                $this->campaign,
                null,
                [
                    'voucher_code' => 'FULL1',
                    'channel' => 'mobile',
                    'source' => 'app',
                    'medium' => 'push',
                    'value_cents' => 20000,
                    'metadata' => ['key' => 'value'],
                ]
            );

            expect($event->voucher_code)->toBe('FULL1');
            expect($event->channel)->toBe('mobile');
            expect($event->source)->toBe('app');
            expect($event->medium)->toBe('push');
            expect($event->value_cents)->toBe(20000);
            expect($event->metadata)->toBe(['key' => 'value']);
        });
    });

    describe('relationships', function (): void {
        it('belongs to campaign', function (): void {
            $event = CampaignEvent::recordImpression($this->campaign);

            expect($event->campaign)->toBeInstanceOf(Campaign::class);
            expect($event->campaign->id)->toBe($this->campaign->id);
        });

        it('belongs to variant when set', function (): void {
            $variant = CampaignVariant::create([
                'campaign_id' => $this->campaign->id,
                'name' => 'Rel Variant',
                'variant_code' => 'REL-V-' . uniqid(),
                'slug' => 'rel-variant',
                'weight' => 100,
            ]);

            $event = CampaignEvent::recordImpression($this->campaign, $variant);

            expect($event->variant)->toBeInstanceOf(CampaignVariant::class);
            expect($event->variant->id)->toBe($variant->id);
        });

        it('returns null variant when not set', function (): void {
            $event = CampaignEvent::recordImpression($this->campaign);

            expect($event->variant)->toBeNull();
        });
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            CampaignEvent::recordImpression($this->campaign);
            CampaignEvent::recordApplication($this->campaign, 'APP1');
            CampaignEvent::recordConversion($this->campaign, 'CONV1', 1000, 100);
        });

        it('filters by event type using ofType scope', function (): void {
            $impressions = CampaignEvent::ofType(CampaignEventType::Impression)->count();
            $applications = CampaignEvent::ofType(CampaignEventType::Application)->count();

            expect($impressions)->toBe(1);
            expect($applications)->toBe(1);
        });

        it('filters impressions using impressions scope', function (): void {
            $count = CampaignEvent::impressions()->count();

            expect($count)->toBe(1);
        });

        it('filters applications using applications scope', function (): void {
            $count = CampaignEvent::applications()->count();

            expect($count)->toBe(1);
        });

        it('filters conversions using conversions scope', function (): void {
            $count = CampaignEvent::conversions()->count();

            expect($count)->toBe(1);
        });

        it('filters by date range using occurredBetween scope', function (): void {
            $start = Carbon::now()->subHour();
            $end = Carbon::now()->addHour();

            $count = CampaignEvent::occurredBetween($start, $end)->count();

            expect($count)->toBe(3);
        });

        it('filters by channel using fromChannel scope', function (): void {
            CampaignEvent::recordImpression($this->campaign, null, ['channel' => 'web']);
            CampaignEvent::recordImpression($this->campaign, null, ['channel' => 'mobile']);

            $webCount = CampaignEvent::fromChannel('web')->count();
            $mobileCount = CampaignEvent::fromChannel('mobile')->count();

            expect($webCount)->toBe(1);
            expect($mobileCount)->toBe(1);
        });

        it('filters by variant using forVariant scope', function (): void {
            $variant = CampaignVariant::create([
                'campaign_id' => $this->campaign->id,
                'name' => 'Scope Variant',
                'variant_code' => 'SCOPE-V-' . uniqid(),
                'slug' => 'scope-variant',
                'weight' => 100,
            ]);

            CampaignEvent::recordImpression($this->campaign, $variant);
            CampaignEvent::recordImpression($this->campaign, $variant);

            $count = CampaignEvent::forVariant($variant)->count();

            expect($count)->toBe(2);
        });
    });

    describe('getTable()', function (): void {
        it('returns table name from config', function (): void {
            $event = new CampaignEvent;

            expect($event->getTable())->toBeString();
        });
    });

    describe('casts', function (): void {
        it('casts event_type to enum', function (): void {
            $event = CampaignEvent::recordImpression($this->campaign);

            expect($event->event_type)->toBeInstanceOf(CampaignEventType::class);
        });

        it('casts value_cents to integer', function (): void {
            $event = CampaignEvent::recordConversion($this->campaign, 'C1', 1234, 123);

            expect($event->value_cents)->toBeInt();
            expect($event->value_cents)->toBe(1234);
        });

        it('casts discount_cents to integer', function (): void {
            $event = CampaignEvent::recordConversion($this->campaign, 'C2', 1000, 567);

            expect($event->discount_cents)->toBeInt();
            expect($event->discount_cents)->toBe(567);
        });

        it('casts metadata to array', function (): void {
            $event = CampaignEvent::recordEvent(
                CampaignEventType::Impression,
                $this->campaign,
                null,
                ['metadata' => ['foo' => 'bar']]
            );

            expect($event->metadata)->toBeArray();
            expect($event->metadata)->toBe(['foo' => 'bar']);
        });

        it('casts occurred_at to datetime', function (): void {
            $event = CampaignEvent::recordImpression($this->campaign);

            expect($event->occurred_at)->toBeInstanceOf(Carbon::class);
        });
    });

    describe('morphTo relationships', function (): void {
        it('has user morphTo relationship', function (): void {
            $event = new CampaignEvent;

            expect(method_exists($event, 'user'))->toBeTrue();
        });

        it('has cart morphTo relationship', function (): void {
            $event = new CampaignEvent;

            expect(method_exists($event, 'cart'))->toBeTrue();
        });

        it('has order morphTo relationship', function (): void {
            $event = new CampaignEvent;

            expect(method_exists($event, 'order'))->toBeTrue();
        });
    });
});
