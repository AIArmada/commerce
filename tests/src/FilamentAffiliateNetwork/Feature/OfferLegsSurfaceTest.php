<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LinksRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\EditAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use Filament\Actions\Action;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class OfferLegsSurfaceHostComponent extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function render()
    {
        return view('livewire.placeholder');
    }
}

function offerLegsSurfaceOffer(): AffiliateOffer
{
    $site = AffiliateSite::create([
        'name' => 'Legs Site',
        'domain' => 'legs-' . uniqid() . '.example.com',
        'status' => AffiliateSite::STATUS_VERIFIED,
        'verified_at' => now(),
    ]);

    return AffiliateOffer::factory()->forSite($site)->create([
        'requires_approval' => false,
    ]);
}

function offerLegsSurfaceLeg(AffiliateOffer $offer): NetworkConversionLeg
{
    $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-' . uniqid())->create();

    return NetworkConversionLeg::factory()->forLink($link)->create([
        'status' => LegStatus::Posted,
    ]);
}

describe('offer legs surface', function (): void {
    test('offer resource registers links and legs relation managers', function (): void {
        expect(AffiliateOfferResource::getRelations())->toBe([
            LinksRelationManager::class,
            LegsRelationManager::class,
        ]);
    });

    test('legs table exposes money columns and the reference', function (): void {
        $table = (new LegsRelationManager)->table(Table::make(new OfferLegsSurfaceHostComponent));

        expect($table->getColumn('external_reference'))->not->toBeNull()
            ->and($table->getColumn('commission_minor'))->not->toBeNull()
            ->and($table->getColumn('fee_minor'))->not->toBeNull()
            ->and($table->getColumn('payout_minor'))->not->toBeNull()
            ->and($table->getColumn('status'))->not->toBeNull();
    });

    test('legs table reverse action reverses a posted leg', function (): void {
        $offer = offerLegsSurfaceOffer();
        $leg = offerLegsSurfaceLeg($offer);

        $host = new OfferLegsSurfaceHostComponent;
        $action = (new LegsRelationManager)->table(Table::make($host))->getAction('reverse');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($leg);
        $action->call(['data' => ['reason' => 'refund']]);

        $companion = NetworkConversionLeg::query()
            ->where('external_reference', $leg->external_reference . ':reversal:refund')
            ->first();

        expect($leg->fresh()->status)->toBe(LegStatus::Reversed)
            ->and($companion)->not->toBeNull()
            ->and($companion->metadata['reverses_id'] ?? null)->toBe((string) $leg->getKey());
    });

    test('offers table exposes the platform fee column', function (): void {
        $table = AffiliateOffersTable::configure(Table::make(new OfferLegsSurfaceHostComponent));

        expect($table->getColumn('network_fee_bp'))->not->toBeNull();
    });

    test('mergeCatalogToken stamps issuance when a token is set', function (): void {
        $data = AffiliateSiteForm::mergeCatalogToken(['catalog_token' => 'tok-secret']);

        expect($data)->not->toHaveKey('catalog_token')
            ->and($data['catalog_token_encrypted'] ?? null)->not->toBeNull()
            ->and($data['catalog_token_issued_at'] ?? null)->not->toBeNull();
    });

    test('mergeCatalogToken leaves issuance alone when no token is set', function (): void {
        expect(AffiliateSiteForm::mergeCatalogToken(['name' => 'No Token']))->toBe(['name' => 'No Token']);
    });

    test('site edit page wires the rotate token header action', function (): void {
        $page = (new ReflectionClass(EditAffiliateSite::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(EditAffiliateSite::class, 'getHeaderActions');

        /** @var array<int, Action> $actions */
        $actions = $method->invoke($page);
        $names = array_map(fn ($action): string => $action->getName(), $actions);

        expect($names)->toContain('rotate_catalog_token');
    });

    test('rotating the catalog token issues a fresh token', function (): void {
        $site = AffiliateSite::create([
            'name' => 'Rotate Site',
            'domain' => 'rotate-' . uniqid() . '.example.com',
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $token = $site->rotateCatalogToken();

        expect($token)->toBeString()
            ->and($site->fresh()->hasCatalogToken())->toBeTrue()
            ->and($site->fresh()->catalog_token_issued_at)->not->toBeNull();
    });
});
