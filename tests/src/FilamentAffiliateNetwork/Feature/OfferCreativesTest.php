<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\EditAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CreativesRelationManager;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Gate::define('affiliate-network.admin', fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $this->offer = AffiliateOffer::factory()->forSite(AffiliateSite::factory()->verified()->create())->create([
        'external_program_id' => 'merchant-program',
    ]);
});

test('creative manager lists imported and manual rows with provenance', function (): void {
    $manual = $this->offer->creatives()->create(['type' => 'text', 'name' => 'Manual']);
    $imported = $this->offer->creatives()->create([
        'type' => 'document', 'name' => 'Merchant guide', 'external_creative_id' => 'guide',
        'source_asset_url' => 'https://merchant.example/guide.pdf',
    ]);
    expect(AffiliateOfferResource::getRelations())->toContain(CreativesRelationManager::class);
    Livewire::test(CreativesRelationManager::class, [
        'ownerRecord' => $this->offer, 'pageClass' => EditAffiliateOffer::class,
    ])->assertCanSeeTableRecords([$manual, $imported])->assertSee('Imported')->assertSee('Manual')->assertSee('merchant-program');
});

test('manual creation uses uploads and imported edits only change operator fields', function (): void {
    $manager = new CreativesRelationManager;
    $manager->ownerRecord = $this->offer;
    $manual = $manager->saveCreative([
        'name' => 'New banner', 'type' => 'banner', 'is_active' => true, 'sort_order' => 1,
        'source_asset_url' => 'https://forged.example/banner.png', 'external_creative_id' => 'forged',
    ]);
    expect($manual->source_asset_url)->toBeNull()->and($manual->external_creative_id)->toBeNull();
    $imported = $this->offer->creatives()->create(['type' => 'image', 'name' => 'Merchant', 'external_creative_id' => 'hero']);
    $manager->saveCreative(['name' => 'Forged', 'type' => 'text', 'is_active' => false, 'sort_order' => 8], $imported);
    expect($imported->fresh()->name)->toBe('Merchant')->and($imported->fresh()->type)->toBe('image')
        ->and($imported->fresh()->is_active)->toBeFalse()->and($imported->fresh()->sort_order)->toBe(8);
});

test('admin creative writes are pinned to the parent offer and its owner', function (): void {
    config(['affiliate-network.owner.enabled' => true]);
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $siteA = OwnerContext::withOwner($ownerA, fn () => AffiliateSite::factory()->verified()->forOwner($ownerA)->create());
    $siteB = OwnerContext::withOwner($ownerB, fn () => AffiliateSite::factory()->verified()->forOwner($ownerB)->create());
    $offerA = OwnerContext::withOwner($ownerA, fn () => AffiliateOffer::factory()->forSite($siteA)->create());
    $offerB = OwnerContext::withOwner($ownerB, fn () => AffiliateOffer::factory()->forSite($siteB)->create());
    $creativeB = OwnerContext::withOwner($ownerB, fn () => $offerB->creatives()->create(['name' => 'B', 'type' => 'text']));
    $manager = new CreativesRelationManager;
    $manager->ownerRecord = $offerA;
    $data = ['name' => 'A', 'type' => 'text', 'is_active' => true, 'sort_order' => 0];
    expect(fn () => $manager->saveCreative($data, $creativeB))->toThrow(ModelNotFoundException::class);
    $created = $manager->saveCreative($data);
    expect($created->offer_id)->toBe($offerA->id);
    OwnerContext::withOwner($ownerB, fn () => expect(AffiliateOfferCreative::find($created->id))->toBeNull());
});

test('creative mutation requires network administrator authorization on every request', function (): void {
    $manager = new CreativesRelationManager;
    $manager->ownerRecord = $this->offer;
    Gate::define('affiliate-network.admin', fn (): bool => false);
    expect(fn () => $manager->saveCreative(['name' => 'Denied', 'type' => 'text', 'is_active' => true, 'sort_order' => 0]))
        ->toThrow(HttpException::class);
    expect($this->offer->creatives()->count())->toBe(0);
});

test('manual creatives can be created with an uploaded file through the relation manager', function (): void {
    Storage::fake('public');
    Livewire::test(CreativesRelationManager::class, [
        'ownerRecord' => $this->offer, 'pageClass' => EditAffiliateOffer::class,
    ])->callAction(TestAction::make('create')->table(), data: [
        'name' => 'Uploaded banner', 'type' => 'banner', 'is_active' => true, 'sort_order' => 0,
        'asset' => [UploadedFile::fake()->image('banner.png')],
    ])->assertHasNoActionErrors();
    $creative = $this->offer->creatives()->firstOrFail();
    expect($creative->getMedia('creative_asset'))->toHaveCount(1)
        ->and($creative->external_creative_id)->toBeNull()
        ->and($creative->getAssetUrl())->not->toBeNull();
});
