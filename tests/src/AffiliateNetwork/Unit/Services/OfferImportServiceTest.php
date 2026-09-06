<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\LocalProgramReader;
use AIArmada\AffiliateNetwork\Services\Catalog\RemoteCatalogClient;
use AIArmada\AffiliateNetwork\Services\OfferImportService;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->site = AffiliateSite::create([
        'name' => 'Import Site',
        'domain' => 'import-' . uniqid() . '.example.com',
        'status' => AffiliateSite::STATUS_VERIFIED,
        'verified_at' => now(),
    ]);

    $this->program = AffiliateProgram::create([
        'name' => 'Import Program',
        'slug' => 'import-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1200,
        'cookie_lifetime_days' => 30,
    ]);

    $this->importer = app(OfferImportService::class);
});

describe('OfferImportService', function (): void {
    test('creates offers from local catalog and skips unchanged on re-sync', function (): void {
        // Seed one promotable via fallback: product rule with `in` conditions.
        AffiliateCommissionRule::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Widget rule',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['WIDGET-1']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 2000,
            'is_active' => true,
        ]);

        $first = $this->importer->sync($this->site, (string) $this->program->getKey());

        expect($first['created'])->toBe(1);
        expect($first['skipped'])->toBe(0);

        $offer = AffiliateOffer::query()->where('site_id', $this->site->getKey())->first();
        expect($offer)->not->toBeNull();
        expect($offer->rate_base_bp)->toBe(2000);
        expect($offer->rate_fixed_minor)->toBeNull();
        expect($offer->subject_key)->toBe('WIDGET-1');
        expect($offer->external_program_id)->toBe((string) $this->program->getKey());
        expect($offer->source_checksum)->not->toBeNull();

        $second = $this->importer->sync($this->site, (string) $this->program->getKey());

        expect($second['created'])->toBe(0);
        expect($second['skipped'])->toBe(1);
    });

    test('re-sync updates rates but never unpublishes operator-published offers', function (): void {
        $rule = AffiliateCommissionRule::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Floating rule',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['FLOAT-1']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 1000,
            'is_active' => true,
        ]);

        $this->importer->sync($this->site, (string) $this->program->getKey());

        $offer = AffiliateOffer::query()->where('site_id', $this->site->getKey())->first();
        $offer->update(['status' => OfferStatus::Published]);

        $rule->update(['commission_value' => 2500]);
        $result = $this->importer->sync($this->site, (string) $this->program->getKey());

        expect($result['updated'])->toBe(1);
        expect($offer->fresh()->rate_base_bp)->toBe(2500);
        expect($offer->fresh()->status)->toBe(OfferStatus::Published);
    });

    test('syncAll mirrors every available program', function (): void {
        $second = AffiliateProgram::create([
            'name' => 'Second Program',
            'slug' => 'second-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'visibility' => ProgramVisibility::Public,
            'requires_approval' => false,
            'commission_type' => CommissionType::Percentage,
            'default_commission_rate_basis_points' => 700,
        ]);

        foreach ([$this->program, $second] as $i => $program) {
            AffiliateCommissionRule::create([
                'program_id' => $program->getKey(),
                'name' => 'Rule ' . $i,
                'rule_type' => CommissionRuleType::Product,
                'priority' => 90,
                'conditions' => ['product_id' => ['in' => ['ITEM-' . $i]]],
                'commission_type' => CommissionType::Percentage,
                'commission_value' => 1100,
                'is_active' => true,
            ]);
        }

        $result = $this->importer->syncAll($this->site);

        expect($result['programs'])->toBeGreaterThanOrEqual(2);
        expect($result['created'])->toBeGreaterThanOrEqual(2);
        expect(AffiliateOffer::query()->where('site_id', $this->site->getKey())->count())
            ->toBeGreaterThanOrEqual(2);
    });

    test('never touches manual offers', function (): void {
        AffiliateOffer::create([
            'site_id' => $this->site->getKey(),
            'name' => 'Manual',
            'slug' => 'manual-' . uniqid(),
            'status' => OfferStatus::Draft,
            'rate_base_bp' => 500,
        ]);

        AffiliateCommissionRule::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Gadget rule',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['GADGET-9']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 900,
            'is_active' => true,
        ]);

        $this->importer->sync($this->site, (string) $this->program->getKey());

        $manual = AffiliateOffer::query()->where('site_id', $this->site->getKey())->whereNull('external_program_id')->first();
        expect($manual->rate_base_bp)->toBe(500);
    });

    test('syncAll keeps good programs when one remote program fails', function (): void {
        Http::fake([
            '*/programs/good-1/catalog' => Http::response([
                'version' => 'v1',
                'program_id' => 'good-1',
                'currency' => 'MYR',
                'cookie_days' => 30,
                'base' => ['commission_type' => 'percentage', 'default_rate_bp' => 1000],
                'subjects' => [[
                    'subject_type' => 'product',
                    'subject_key' => 'REMOTE-1',
                    'title' => 'Remote Widget',
                    'url' => 'https://merchant.test/x/remote-1',
                    'effective' => ['commission_type' => 'percentage', 'rate_bp' => 1500, 'applied_rule_ids' => []],
                ]],
                'variable_extras' => ['volume_tiers' => [], 'promotions' => []],
            ]),
            '*/programs/bad-1/catalog' => Http::response(['message' => 'gone'], 410),
            '*/programs' => Http::response(['data' => [
                ['program_id' => 'good-1', 'name' => 'Good'],
                ['program_id' => 'bad-1', 'name' => 'Bad'],
            ]]),
        ]);

        $site = AffiliateSite::create([
            'name' => 'Remote Import Site',
            'domain' => 'remote-import-' . uniqid() . '.example.com',
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verified_at' => now(),
            'catalog_url' => 'https://merchant.test/api/affiliates',
        ]);

        $importer = new OfferImportService(
            app(LocalProgramReader::class),
            new RemoteCatalogClient(new PublicHttpUrlGuard(dnsResolver: fn (string $host): array => ['93.184.216.34'])),
            app(CreateOffer::class),
            app(UpdateOffer::class),
        );

        $result = $importer->syncAll($site);

        expect($result['created'])->toBe(1);
        expect($result['failed'])->toBe(1);
        expect($site->fresh()->sync_status)->toBe('partial');
        expect(AffiliateOffer::query()->where('site_id', $site->getKey())->where('subject_key', 'REMOTE-1')->exists())->toBeTrue();
    });

    test('sync holds operator-overridden rates and unlock re-applies catalog rates', function (): void {
        $rule = AffiliateCommissionRule::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Lock rule',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['LOCK-1']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 1000,
            'is_active' => true,
        ]);

        $this->importer->sync($this->site, (string) $this->program->getKey());

        $offer = AffiliateOffer::query()->where('site_id', $this->site->getKey())->first();
        expect($offer->rate_source)->toBe('synced');

        // Operator override in Filament locks the rate block.
        $offer->update(['rate_base_bp' => 3000]);
        expect($offer->fresh()->rate_source)->toBe('manual');

        // Merchant moves the rate; sync must hold the operator value back.
        $rule->update(['commission_value' => 2500]);
        $held = $this->importer->sync($this->site, (string) $this->program->getKey());

        expect($held['locked'])->toBe(1);
        expect($held['updated'])->toBe(0);
        expect($offer->fresh()->rate_base_bp)->toBe(3000);
        expect($offer->fresh()->rate_source)->toBe('manual');

        // Same snapshot again: checksum stamped, so it skips quietly.
        $again = $this->importer->sync($this->site, (string) $this->program->getKey());
        expect($again['locked'])->toBe(0);
        expect($again['skipped'])->toBe(1);

        // Explicit unlock re-applies catalog rates on next sync.
        $offer->update(['rate_source' => 'synced']);
        $released = $this->importer->sync($this->site, (string) $this->program->getKey());

        expect($released['updated'])->toBe(1);
        expect($offer->fresh()->rate_base_bp)->toBe(2500);
        expect($offer->fresh()->rate_source)->toBe('synced');
    });

    test('sync never silently unlocks a manual offer when rates agree', function (): void {
        $rule = AffiliateCommissionRule::create([
            'program_id' => $this->program->getKey(),
            'name' => 'Agree rule',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['AGREE-1']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 1000,
            'is_active' => true,
        ]);

        $this->importer->sync($this->site, (string) $this->program->getKey());

        $offer = AffiliateOffer::query()->where('site_id', $this->site->getKey())->first();

        // Operator independently lands on the same value merchants publish.
        $offer->update(['rate_base_bp' => 2500]);
        expect($offer->fresh()->rate_source)->toBe('manual');

        $rule->update(['commission_value' => 2500]);
        $result = $this->importer->sync($this->site, (string) $this->program->getKey());

        expect($result['locked'])->toBe(0);
        expect($result['updated'])->toBe(1);
        expect($offer->fresh()->rate_base_bp)->toBe(2500);
        expect($offer->fresh()->rate_source)->toBe('manual');
    });
});
