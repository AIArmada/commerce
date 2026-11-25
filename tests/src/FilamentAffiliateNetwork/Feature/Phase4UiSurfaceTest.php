<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferInvitation;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\DealsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\InvitationsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Pages\ListMerchantStatements;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Tables\MerchantStatementsTable;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class PhasefourHostComponent extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

beforeEach(function (): void {
    Gate::define('affiliate-network.admin', fn (): bool => true);
    config(['filament-affiliate-network.authorization.admin_ability' => 'affiliate-network.admin']);
    $this->actingAs(User::factory()->create());
});

function phasefourSite(): AffiliateSite
{
    return AffiliateSite::factory()->verified()->create([
        'domain' => 'phase4-' . uniqid() . '.example.com',
    ]);
}

describe('MerchantStatementResource', function (): void {
    test('registers list and view pages for network administrators', function (): void {
        expect(MerchantStatementResource::getPages())
            ->toHaveKey('index')
            ->toHaveKey('view')
            ->and(MerchantStatementResource::canViewAny())->toBeTrue();
    });

    test('navigation is gated on the admin ability', function (): void {
        config(['filament-affiliate-network.authorization.admin_ability' => '']);

        expect(MerchantStatementResource::canViewAny())->toBeFalse()
            ->and(MerchantStatementResource::shouldRegisterNavigation())->toBeFalse();
    });

    test('table exposes the settlement columns and lifecycle actions', function (): void {
        $table = MerchantStatementsTable::configure(Table::make(new PhasefourHostComponent));

        expect($table->getColumn('statement_number'))->not->toBeNull()
            ->and($table->getColumn('payout_minor'))->not->toBeNull()
            ->and($table->getColumn('status'))->not->toBeNull()
            ->and($table->getAction('issue'))->not->toBeNull()
            ->and($table->getAction('mark_paid'))->not->toBeNull()
            ->and($table->getAction('void'))->not->toBeNull();
    });

    test('list page wires the generate header action', function (): void {
        $page = (new ReflectionClass(ListMerchantStatements::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ListMerchantStatements::class, 'getHeaderActions');

        $names = array_map(fn ($action): string => $action->getName(), $method->invoke($page));

        expect($names)->toContain('generate');
    });

    test('issue action moves a draft statement to issued', function (): void {
        $statement = MerchantStatement::factory()->forSite(phasefourSite())->draft()->create();

        $host = new PhasefourHostComponent;
        $action = MerchantStatementsTable::configure(Table::make($host))->getAction('issue');
        $action->livewire($host);
        $action->record($statement);
        $action->call();

        expect($statement->fresh()->status)->toBe(SettlementStatus::Issued)
            ->and($statement->fresh()->issued_at)->not->toBeNull();
    });

    test('mark paid action records the payment reference', function (): void {
        $statement = MerchantStatement::factory()->forSite(phasefourSite())->issued()->create();

        $host = new PhasefourHostComponent;
        $action = MerchantStatementsTable::configure(Table::make($host))->getAction('mark_paid');
        $action->livewire($host);
        $action->record($statement);
        $action->call(['data' => ['payment_reference' => 'WIRE-9001']]);

        expect($statement->fresh()->status)->toBe(SettlementStatus::Paid)
            ->and($statement->fresh()->payment_reference)->toBe('WIRE-9001');
    });

    test('void action retracts a draft statement', function (): void {
        $statement = MerchantStatement::factory()->forSite(phasefourSite())->draft()->create();

        $host = new PhasefourHostComponent;
        $action = MerchantStatementsTable::configure(Table::make($host))->getAction('void');
        $action->livewire($host);
        $action->record($statement);
        $action->call();

        expect($statement->fresh()->status)->toBe(SettlementStatus::Void);
    });

    test('issue action is hidden once a statement left draft', function (): void {
        $host = new PhasefourHostComponent;
        $table = MerchantStatementsTable::configure(Table::make($host));
        $issued = MerchantStatement::factory()->forSite(phasefourSite())->issued()->create();

        $action = $table->getAction('issue');
        $action->livewire($host);
        $action->record($issued);

        expect($action->isVisible())->toBeFalse();
    });
});

describe('offer marketplace relation managers', function (): void {
    test('invitations relation manager lists tokens and exposes invite and revoke', function (): void {
        $manager = new InvitationsRelationManager;
        $table = $manager->table(Table::make(new PhasefourHostComponent));

        expect($table->getColumn('token'))->not->toBeNull()
            ->and($table->getHeaderActions())->toHaveCount(1)
            ->and($table->getAction('revoke'))->not->toBeNull();
    });

    test('invite action creates an invitation token', function (): void {
        $offer = AffiliateOffer::factory()->forSite(phasefourSite())->create();
        $manager = new InvitationsRelationManager;
        $manager->ownerRecord = $offer;

        $action = $manager->table(Table::make(new PhasefourHostComponent))->getHeaderActions()[0];
        $action->record(null);
        $action->call(['data' => ['affiliate_id' => '', 'auto_approve' => true]]);

        expect(AffiliateOfferInvitation::query()->where('offer_id', $offer->getKey())->count())->toBe(1);
    });

    test('deals relation manager exposes override columns and revoke', function (): void {
        $manager = new DealsRelationManager;
        $table = $manager->table(Table::make(new PhasefourHostComponent));

        expect($table)->not->toBeNull()
            ->and($table->getColumn('rate_base_bp'))->not->toBeNull()
            ->and($table->getColumn('network_fee_bp'))->not->toBeNull()
            ->and($table->getAction('revoke'))->not->toBeNull();
    });

    test('upsert deal action creates an override row', function (): void {
        $offer = AffiliateOffer::factory()->forSite(phasefourSite())->create();
        $manager = new DealsRelationManager;
        $manager->ownerRecord = $offer;

        $action = $manager->table(Table::make(new PhasefourHostComponent))->getHeaderActions()[0];
        $action->record(null);
        $action->call(['data' => ['affiliate_id' => 'aff-deal-1', 'rate_base_bp' => 2200]]);

        $deal = AffiliateOfferDeal::query()->where('offer_id', $offer->getKey())->sole();

        expect($deal->rate_base_bp)->toBe(2200)
            ->and($deal->affiliate_id)->toBe('aff-deal-1');
    });

    test('coupons relation manager exposes assign and revoke', function (): void {
        $manager = new CouponsRelationManager;
        $table = $manager->table(Table::make(new PhasefourHostComponent));

        expect($table->getColumn('code'))->not->toBeNull()
            ->and($table->getHeaderActions())->toHaveCount(1)
            ->and($table->getAction('revoke'))->not->toBeNull();
    });
});

describe('site settlement & locking form', function (): void {
    test('exposes settlement, locking, and prepaid fields', function (): void {
        $schema = AffiliateSiteForm::configure(
            Schema::make(new PhasefourHostComponent)->model(AffiliateSite::class)->statePath('data')
        );

        foreach ([
            'default_locking_days',
            'require_settled_for_payout',
            'monthly_spend_cap_minor',
            'prepaid_balance_minor',
        ] as $field) {
            expect($schema->getComponent($field))->not->toBeNull();
        }
    });

    test('domain field validates bare hostnames through the domain guards', function (): void {
        $schema = AffiliateSiteForm::configure(
            Schema::make(new PhasefourHostComponent)->model(AffiliateSite::class)->statePath('data')
        );
        $component = $schema->getComponent('domain');

        $rules = $component->getValidationRules();

        expect($rules)->not->toBeEmpty()
            ->and(collect($rules)->contains(fn ($rule): bool => $rule instanceof Closure))->toBeTrue();
    });
});

describe('legs relation manager settlement actions', function (): void {
    test('adjust action updates a posted leg with an adjustment companion', function (): void {
        $site = phasefourSite();
        $offer = AffiliateOffer::factory()->forSite($site)->create(['requires_approval' => false]);
        $leg = NetworkConversionLeg::factory()->forOffer($offer)->create([
            'status' => LegStatus::Posted,
            'revenue_minor' => 10000,
            'commission_minor' => 1000,
            'payout_minor' => 1000,
            'fee_minor' => 0,
        ]);

        $host = new PhasefourHostComponent;
        $action = (new LegsRelationManager)->table(Table::make($host))->getAction('adjust');
        $action->livewire($host);
        $action->record($leg);
        $action->call(['data' => [
            'adjustment_reference' => 'ADJ-1',
            'delta_revenue_minor' => -2000,
            'reason' => 'partial refund',
        ]]);

        $adjustment = NetworkConversionLeg::query()
            ->where('parent_leg_id', $leg->getKey())
            ->sole();

        expect($adjustment->revenue_minor)->toBe(-2000)
            ->and($adjustment->metadata['adjustment_reference'])->toBe('ADJ-1');
    });

    test('lock matured header action locks pending legs past their window', function (): void {
        $site = phasefourSite();
        $offer = AffiliateOffer::factory()->forSite($site)->create();
        $leg = NetworkConversionLeg::factory()->forOffer($offer)->create([
            'status' => LegStatus::Pending,
            'locks_at' => now()->subDay(),
        ]);

        $host = new PhasefourHostComponent;
        $action = (new LegsRelationManager)->table(Table::make($host))->getHeaderActions()[0];

        expect($action->getName())->toBe('lock_matured');

        $action->livewire($host);
        $action->call();

        expect($leg->fresh()->status)->toBe(LegStatus::Posted);
    });

    test('settlement fields surface on the site record builder', function (): void {
        $site = AffiliateSite::factory()->verified()->create([
            'domain' => 'settle-' . uniqid() . '.example.com',
            'default_locking_days' => 14,
            'require_settled_for_payout' => true,
            'monthly_spend_cap_minor' => 500000,
            'prepaid_balance_minor' => 250000,
        ]);

        $record = app(SitesApi::class)->findByIdUnscoped((string) $site->getKey());

        expect($record->defaultLockingDays)->toBe(14)
            ->and($record->requireSettledForPayout)->toBeTrue()
            ->and($record->monthlySpendCapMinor)->toBe(500000)
            ->and($record->prepaidBalanceMinor)->toBe(250000);
    });
});
