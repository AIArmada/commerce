<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SyncSiteOffers;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\CreateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\LinksRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Tables\AffiliateSitesTable;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class CatalogOperationsHostComponent extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

function catalogOperationsSite(): AffiliateSite
{
    return AffiliateSite::factory()->verified()->create([
        'name' => 'Catalog Site',
        'domain' => 'catalog-' . uniqid() . '.example.com',
    ]);
}

function catalogOperationsProgram(): AffiliateProgram
{
    return AffiliateProgram::create([
        'name' => 'Catalog Program',
        'slug' => 'catalog-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1200,
        'cookie_lifetime_days' => 30,
    ]);
}

describe('site catalog sync operations', function (): void {
    test('site form exposes catalog url and token fields', function (): void {
        $schema = AffiliateSiteForm::configure(
            Schema::make(new CatalogOperationsHostComponent)->model(AffiliateSite::class)->statePath('data')
        );

        expect($schema->getComponent('catalog_url'))->not->toBeNull()
            ->and($schema->getComponent('catalog_token'))->not->toBeNull();
    });

    test('site actions encrypt catalog tokens and preserve blanks', function (): void {
        $site = app(CreateSite::class)->execute([
            'name' => 'Token Site',
            'domain' => 'token-' . uniqid() . '.example.com',
            'catalog_token' => 'secret-token',
        ]);

        expect($site->catalog_token_encrypted)->not->toBe('secret-token')
            ->and(decrypt($site->catalog_token_encrypted))->toBe('secret-token');

        $updated = app(UpdateSite::class)->execute($site, [
            'name' => 'Token Site',
            'domain' => $site->domain,
            'catalog_token' => '',
        ]);

        expect($updated->catalog_token_encrypted)->toBe($site->catalog_token_encrypted);
    });

    test('sync catalog action imports local offers', function (): void {
        $site = catalogOperationsSite();
        $program = catalogOperationsProgram();

        AffiliateCommissionRule::create([
            'program_id' => $program->getKey(),
            'name' => 'Sync rule',
            'rule_type' => CommissionRuleType::Product,
            'priority' => 90,
            'conditions' => ['product_id' => ['in' => ['SYNC-1']]],
            'commission_type' => CommissionType::Percentage,
            'commission_value' => 2000,
            'is_active' => true,
        ]);

        $host = new CatalogOperationsHostComponent;
        $action = AffiliateSitesTable::configure(Table::make($host))->getAction('sync_catalog');

        expect($action)->not->toBeNull();

        $result = app(SyncSiteOffers::class)->execute($site->toRecord());

        expect($result['programs'])->toBe(1)
            ->and($result['created'])->toBe(1)
            ->and(AffiliateOffer::query()->where('site_id', $site->getKey())->count())->toBe(1);
    });
});

describe('offer catalog operations', function (): void {
    test('offer form exposes a volume tiers repeater', function (): void {
        $schema = AffiliateOfferForm::configure(
            Schema::make(new CatalogOperationsHostComponent)->model(AffiliateOffer::class)->statePath('data')
        );

        expect($schema->getComponent('volume_tiers'))->toBeInstanceOf(Repeater::class);
    });

    test('links relation manager exposes a reconcile action', function (): void {
        $site = catalogOperationsSite();

        $offer = AffiliateOffer::factory()->forSite($site)->create([
            'landing_url' => 'https://catalog.example.com/landing',
            'requires_approval' => false,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'RECON' . uniqid(),
            'name' => 'Recon Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create();

        $manager = new LinksRelationManager;
        $table = $manager->table(Table::make(new CatalogOperationsHostComponent));

        expect($table->getAction('reconcile'))->not->toBeNull()
            ->and($link->exists)->toBeTrue();
    });
});
