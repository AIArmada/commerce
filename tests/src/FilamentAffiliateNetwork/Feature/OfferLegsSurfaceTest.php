<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\CreateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CreativesRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\DealsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\InvitationsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LinksRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\EditAffiliateSite;
use Filament\Actions\Action;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class FilamentOfferHarness extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

describe('offer legs surface', function (): void {
    test('offer resource registers links, legs, creatives and marketplace relation managers', function (): void {
        expect(AffiliateOfferResource::getRelations())->toBe([
            LinksRelationManager::class,
            LegsRelationManager::class,
            CreativesRelationManager::class,
            InvitationsRelationManager::class,
            DealsRelationManager::class,
            CouponsRelationManager::class,
        ]);
    });

    test('legs table exposes money columns and the reference', function (): void {
        $table = (new LegsRelationManager)->table(Table::make(new FilamentOfferHarness));

        expect($table->getColumn('external_reference'))->not->toBeNull()
            ->and($table->getColumn('commission_minor'))->not->toBeNull()
            ->and($table->getColumn('fee_minor'))->not->toBeNull()
            ->and($table->getColumn('payout_minor'))->not->toBeNull()
            ->and($table->getColumn('status'))->not->toBeNull();
    });

    test('legs table reverse action reverses a posted leg', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'legs-' . uniqid() . '.example']);
        $offer = AffiliateOffer::factory()->forSite($site)->create(['requires_approval' => false]);
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId('aff-' . uniqid())
            ->create();
        $leg = NetworkConversionLeg::factory()
            ->forLink($link)
            ->create(['status' => LegStatus::Posted]);

        $host = new FilamentOfferHarness;
        $action = (new LegsRelationManager)->table(Table::make($host))->getAction('reverse');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($leg);
        $action->call(['data' => ['reason' => 'refund']]);

        $companion = NetworkConversionLeg::query()
            ->where('metadata->reverses_id', (string) $leg->getKey())
            ->first();

        expect($leg->fresh()->status)->toBe(LegStatus::Reversed)
            ->and($companion)->not->toBeNull()
            ->and($companion->metadata['reversal_reason'] ?? null)->toBe('refund');
    });

    test('credits table exposes the platform fee column', function (): void {
        $table = AffiliateOffersTable::configure(Table::make(new FilamentOfferHarness));

        expect($table->getColumn('network_fee_bp'))->not->toBeNull();
    });

    test('site creation encrypts the catalog token', function (): void {
        $site = app(CreateSite::class)->execute([
            'name' => 'Token Site',
            'domain' => 'token-' . uniqid() . '.example.com',
            'catalog_token' => 'tok-secret',
        ]);

        expect($site->catalog_token_encrypted)->not->toBeNull()
            ->and(decrypt($site->catalog_token_encrypted))->toBe('tok-secret')
            ->and($site->catalog_token_issued_at)->not->toBeNull();
    });

    test('a blank catalog token leaves the stored token alone', function (): void {
        $site = app(CreateSite::class)->execute([
            'name' => 'Token Site',
            'domain' => 'token-' . uniqid() . '.example.com',
            'catalog_token' => 'tok-secret',
        ]);

        $updated = app(UpdateSite::class)->execute($site, [
            'name' => $site->name,
            'domain' => $site->domain,
            'catalog_token' => '',
        ]);

        expect($updated->catalog_token_encrypted)->toBe($site->catalog_token_encrypted);
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
            'status' => SiteStatus::Verified,
            'verified_at' => now(),
        ]);

        $token = app(RotateCatalogToken::class)->execute($site);

        expect($token)->toBeString()
            ->and($site->fresh()->hasCatalogToken())->toBeTrue()
            ->and($site->fresh()->catalog_token_issued_at)->not->toBeNull();
    });
});
