<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\CatalogReader;
use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Drivers\RemoteCatalogHttpDriver;
use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateCreativeLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Queries\CreativePayloadQuery;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\PublishOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncOfferCreatives;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncSiteOffers;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\Links\Actions\GenerateLinkUrl;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

beforeEach(function (): void {
    app()->register(MediaLibraryServiceProvider::class);
    if (! Schema::hasTable('media')) {
        (require base_path('vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub'))->up();
    }
    Storage::fake('public');
    $this->site = AffiliateSite::factory()->verified()->create(['domain' => 'merchant.example']);
    $this->program = AffiliateProgram::create([
        'name' => 'Creative program', 'slug' => 'creative-program',
        'status' => ProgramStatus::Active, 'visibility' => ProgramVisibility::Public,
        'requires_approval' => false, 'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
    ]);
    AffiliateCommissionRule::create([
        'program_id' => $this->program->id, 'name' => 'Products',
        'rule_type' => CommissionRuleType::Product, 'priority' => 90,
        'conditions' => ['product_id' => ['in' => ['one', 'two']]],
        'commission_type' => 'percentage', 'commission_value' => 1200, 'is_active' => true,
    ]);
    $this->creative = AffiliateProgramCreative::create([
        'program_id' => $this->program->id, 'type' => 'image', 'name' => 'Hero',
        'destination_url' => 'https://merchant.example/hero', 'tracking_code' => 'hero',
    ]);
    $this->creative->addMedia(UploadedFile::fake()->image('hero.png'))->toMediaCollection('creative_asset');
    $this->importer = app(SyncSiteOffers::class);
});

test('catalog emits raw program assets without affiliate embeds or general creatives', function (): void {
    AffiliateProgramCreative::create(['type' => 'text_link', 'name' => 'General', 'tracking_code' => 'general']);
    $text = AffiliateProgramCreative::create([
        'program_id' => $this->program->id, 'type' => 'text_link', 'name' => 'Text', 'tracking_code' => 'text',
    ]);
    $before = [AffiliateAttribution::count(), AffiliateConversion::count()];
    $snapshot = app(MerchantCatalog::class)->previewById((string) $this->program->getKey())->payload;
    expect([AffiliateAttribution::count(), AffiliateConversion::count()])->toBe($before);
    $creatives = collect($snapshot['creatives'])->keyBy('id');
    expect($snapshot['version'])->toBe('v2')
        ->and($creatives)->toHaveCount(2)
        ->and($creatives[$this->creative->id]['asset_url'])->toBe($this->creative->getAssetUrl())
        ->and($creatives[$text->id]['asset_url'])->toBeNull()
        ->and($creatives[$this->creative->id])->not->toHaveKey('html_code');
});

test('mirrors assets to every subject and reconciles edits and deletion without touching manual rows or rates', function (): void {
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['created'])->toBe(2);
    $offer = AffiliateOffer::firstOrFail();
    $mirrored = $offer->creatives()->firstOrFail();
    $mirrored->update(['is_active' => false, 'sort_order' => 17]);
    $manual = $offer->creatives()->create(['type' => 'text', 'name' => 'Manual']);
    $checksum = $offer->source_checksum;
    expect(AffiliateOfferCreative::where('external_creative_id', $this->creative->id)->count())->toBe(2)
        ->and($mirrored->source_asset_url)->toBe($this->creative->getAssetUrl())
        ->and($mirrored->type)->toBe(CreativeType::Image);
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['skipped'])->toBe(2);
    $this->creative->update(['name' => 'New hero', 'type' => 'document']);
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['updated'])->toBe(2);
    expect($mirrored->fresh()->name)->toBe('New hero')
        ->and($mirrored->fresh()->type)->toBe(CreativeType::Document)
        ->and($mirrored->fresh()->is_active)->toBeFalse()
        ->and($mirrored->fresh()->sort_order)->toBe(17)
        ->and($offer->fresh()->source_checksum)->toBe($checksum)
        ->and($offer->fresh()->rate_base_bp)->toBe(1200);
    $this->creative->delete();
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['updated'])->toBe(2);
    expect(AffiliateOfferCreative::whereNotNull('external_creative_id')->count())->toBe(0)
        ->and($manual->fresh())->not->toBeNull();
});

test('malformed creative snapshots fail subjects atomically and preserve the last good mirror', function (): void {
    $this->importer->execute($this->site->toRecord(), $this->program->id);
    $before = AffiliateOffer::firstOrFail()->creatives_checksum;
    $this->creative->update(['type' => 'unsupported']);
    $result = $this->importer->execute($this->site->toRecord(), $this->program->id);
    expect($result['failed'])->toBe(2)
        ->and($this->site->fresh()->sync_status->value)->toBe('partial')
        ->and(AffiliateOfferCreative::where('type', CreativeType::Image)->count())->toBe(2)
        ->and(AffiliateOffer::firstOrFail()->creatives_checksum)->toBe($before);
});

test('creative links use the network tracker and the creative destination', function (): void {
    $this->importer->execute($this->site->toRecord(), $this->program->id);
    $offer = AffiliateOffer::firstOrFail();
    $offer->update(['requires_approval' => false]);
    $offer = app(PublishOffer::class)->execute($offer);
    $creative = $offer->creatives()->firstOrFail();
    $link = app(CreateCreativeLink::class)->execute((string) $creative->getKey(), 'affiliate-one');
    $url = GenerateLinkUrl::run($link->link);
    expect($url)->toContain('/go/')
        ->and($url)->not->toContain('signature=')
        ->and($link->link->destination_url)->toBe('https://merchant.example/hero')
        ->and($link->link->parameters)->toHaveKey('anl')
        ->and(app(CreativePayloadQuery::class)->execute((string) $creative->getKey(), (string) $link->getKey())['tracking_url'])->toBe($url)
        ->and(app(CreativePayloadQuery::class)->execute((string) $creative->getKey(), (string) $link->getKey())['embed_code'])->toContain(htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
    $creative->update(['is_active' => false]);
    expect(fn () => app(CreateCreativeLink::class)->execute((string) $creative->getKey(), 'affiliate-one'))->toThrow(RuntimeException::class);
});

test('a creative write failure rolls back offer rates and sync markers', function (): void {
    $this->importer->execute($this->site->toRecord(), $this->program->id);
    $offer = AffiliateOffer::firstOrFail();
    $this->creative->update(['name' => 'Fail saving']);
    AffiliateCommissionRule::query()->update(['commission_value' => 2200]);
    AffiliateOfferCreative::saving(function (): void {
        throw new RuntimeException('Storage unavailable');
    });
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['failed'])->toBe(2)
        ->and($offer->fresh()->rate_base_bp)->toBe(1200)
        ->and($offer->fresh()->source_checksum)->toBe($offer->source_checksum)
        ->and($offer->fresh()->creatives_checksum)->toBe($offer->creatives_checksum)
        ->and($offer->creatives()->firstOrFail()->name)->toBe('Hero');
});

test('manual media is replaced and cleaned up when its offer is deleted', function (): void {
    $offer = AffiliateOffer::factory()->forSite($this->site)->create();
    $creative = $offer->creatives()->create(['type' => 'banner', 'name' => 'Manual']);
    expect($creative->getAssetUrl())->toBeNull();
    $first = $creative->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection('creative_asset');
    $firstPath = $first->getPathRelativeToRoot();
    $creative->refresh();
    $second = $creative->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection('creative_asset');
    Storage::disk('public')->assertMissing($firstPath);
    expect($creative->fresh()->getAssetUrl())->toBe($second->getFullUrl());
    $offer->delete();
    Storage::disk('public')->assertMissing($second->getPathRelativeToRoot());
    expect($creative->fresh())->toBeNull();
});

test('program visibility and cross-owner creative reads and writes fail closed', function (): void {
    $this->program->update(['visibility' => ProgramVisibility::Private]);
    expect(fn () => $this->importer->execute($this->site->toRecord(), $this->program->id))
        ->toThrow(OfferNotFoundException::class);
    config(['affiliate-network.owner.enabled' => true, 'affiliates.owner.enabled' => true]);
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $site = OwnerContext::withOwner(
        $ownerA,
        fn () => AffiliateSite::factory()->forOwner($ownerA)->verified()->create()
    );
    $offer = OwnerContext::withOwner(
        $ownerA,
        fn () => AffiliateOffer::factory()->forSite($site)->create()
    );
    $creative = OwnerContext::withOwner(
        $ownerA,
        fn () => $offer->creatives()->create(['name' => 'Owner A', 'type' => 'text'])
    );
    OwnerContext::withOwner($ownerB, function () use ($offer, $creative): void {
        expect(AffiliateOfferCreative::find($creative->id))->toBeNull()
            ->and(fn () => app(SyncOfferCreatives::class)->execute($offer, []))
            ->toThrow(ModelNotFoundException::class);
    });
    expect(fn () => $this->importer->execute($site->toRecord(), $this->program->id))
        ->toThrow(OfferNotFoundException::class);
});

test('file-less text links and documents mirror without losing types and rate locks preserve creative updates', function (): void {
    $text = AffiliateProgramCreative::query()->create(['program_id' => $this->program->id, 'type' => 'text_link', 'name' => 'Text', 'tracking_code' => 'text']);
    AffiliateProgramCreative::query()->create(['program_id' => $this->program->id, 'type' => 'document', 'name' => 'Document', 'tracking_code' => 'doc']);
    $this->importer->execute($this->site->toRecord(), $this->program->id);
    $offer = AffiliateOffer::firstOrFail();
    expect($offer->creatives()->where('external_creative_id', $text->id)->firstOrFail()->type)->toBe(CreativeType::Text)
        ->and($offer->creatives()->where('type', CreativeType::Document)->firstOrFail()->getAssetUrl())->toBeNull();
    app(UpdateOffer::class)->execute($offer, ['rate_base_bp' => 9000]);
    $this->creative->update(['name' => 'Locked rate hero']);
    AffiliateCommissionRule::query()->update(['commission_value' => 2200]);
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['locked'])->toBe(1)
        ->and($offer->fresh()->rate_base_bp)->toBe(9000)
        ->and($offer->creatives()->where('external_creative_id', $this->creative->id)->firstOrFail()->name)->toBe('Locked rate hero');
});

test('remote catalogs mirror public asset URLs without downloading files', function (): void {
    $snapshot = app(MerchantCatalog::class)->previewById((string) $this->program->getKey())->payload;
    $this->site->update(['catalog_url' => 'https://merchant.example/api/affiliates']);
    Http::fake(['*' => Http::response($snapshot)]);
    app()->instance(CatalogReader::class, new RemoteCatalogHttpDriver(
        new PublicHttpUrlGuard(dnsResolver: fn (string $host): array => ['93.184.216.34']),
    ));
    expect($this->importer->execute($this->site->toRecord(), $this->program->id)['created'])->toBe(2)
        ->and(AffiliateOfferCreative::firstOrFail()->source_asset_url)->toBe($this->creative->getAssetUrl());
    Http::assertSentCount(1);
});
