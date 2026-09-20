<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Services\ProgramCatalogService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\FilamentAffiliates\Actions\RunScheduledPayoutSweep;
use AIArmada\FilamentAffiliates\Pages\ManageAffiliatePayoutSettings;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Pages\ViewAffiliateProgram;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Schemas\AffiliateProgramInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class AdapterOperationsHostComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

class AdapterOperationsTableHost extends Component implements HasTable
{
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table;
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.placeholder');
    }
}

function adapterOperationsProgram(): AffiliateProgram
{
    return AffiliateProgram::create([
        'name' => 'Adapter Program',
        'slug' => 'adapter-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1200,
        'cookie_lifetime_days' => 30,
    ]);
}

function adapterPageHeaderActions(string $pageClass): array
{
    $method = new ReflectionMethod($pageClass, 'getHeaderActions');
    $method->setAccessible(true);

    return $method->invoke((new ReflectionClass($pageClass))->newInstanceWithoutConstructor());
}

it('program view page exposes a catalog preview action', function (): void {
    $actions = adapterPageHeaderActions(ViewAffiliateProgram::class);
    $names = array_map(fn ($action): string => $action->getName(), $actions);

    expect($names)->toContain('preview_catalog');
});

it('catalog preview renders the program snapshot subjects', function (): void {
    $program = adapterOperationsProgram();
    $snapshot = app(ProgramCatalogService::class)->snapshot($program);

    $html = view('filament-affiliates::catalog-preview', ['snapshot' => $snapshot])->render();

    expect($html)->toContain('exact snapshot')
        ->and($html)->toContain('0 subject(s)');
});

it('batch page exposes a settlement export action', function (): void {
    $actions = adapterPageHeaderActions(PayoutBatchPage::class);
    $names = array_map(fn ($action): string => $action->getName(), $actions);

    expect($names)->toContain('export_settlement');
});

it('tax document infolist shows the document currency', function (): void {
    $schema = AffiliateTaxDocumentResource::infolist(
        Schema::make(new AdapterOperationsHostComponent)->model(AffiliateTaxDocument::class)->statePath('data')
    );

    expect($schema->getComponent('currency'))->not->toBeNull();
});

it('program form exposes the program currency', function (): void {
    $schema = AffiliateProgramResource::form(
        Schema::make(new AdapterOperationsHostComponent)->model(AffiliateProgram::class)->statePath('data')
    );

    expect($schema->getComponent('currency'))->not->toBeNull();
});

it('program table shows the program currency', function (): void {
    $table = AffiliateProgramResource::table(Table::make(new AdapterOperationsTableHost));
    $names = array_map(fn ($column): string => $column->getName(), $table->getColumns());

    expect($names)->toContain('currency');
});

it('program infolist shows the program currency', function (): void {
    $schema = AffiliateProgramInfolist::configure(
        Schema::make(new AdapterOperationsHostComponent)->model(AffiliateProgram::class)->statePath('data')
    );

    expect($schema->getComponent('currency'))->not->toBeNull();
});

it('batch page exposes a scheduled sweep action', function (): void {
    $actions = adapterPageHeaderActions(PayoutBatchPage::class);
    $names = array_map(fn ($action): string => $action->getName(), $actions);

    expect($names)->toContain('scheduled_sweep');
});

function adapterSweepAffiliate(): Affiliate
{
    $affiliate = Affiliate::create([
        'code' => 'SWEEP-' . uniqid(),
        'name' => 'Sweep Test Affiliate',
        'contact_email' => 'sweep@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'available_minor' => 10000,
        'pending_minor' => 0,
        'minimum_payout_minor' => 5000,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-' . uniqid(),
        'subtotal_minor' => 5000,
        'total_minor' => 5000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subDay(),
        'affiliate_payout_id' => null,
    ]);

    return $affiliate;
}

it('sweep dry run counts eligible balances without writing', function (): void {
    adapterSweepAffiliate();

    $summary = app(RunScheduledPayoutSweep::class)->handle(dryRun: true);

    expect($summary['processed'])->toBe(1)
        ->and($summary['errors'])->toBe(0)
        ->and(AffiliatePayout::query()->count())->toBe(0);
});

it('sweep run claims payouts for eligible balances', function (): void {
    $affiliate = adapterSweepAffiliate();

    $summary = app(RunScheduledPayoutSweep::class)->handle(dryRun: false);

    expect($summary['processed'])->toBe(1)
        ->and($summary['errors'])->toBe(0)
        ->and(AffiliatePayout::query()->where('payee_id', $affiliate->id)->count())->toBe(1)
        ->and(AffiliateBalance::query()->where('affiliate_id', $affiliate->id)->value('available_minor'))->toBe(5000);
});

it('sweep respects the affiliate filter and minimum floor', function (): void {
    $affiliate = adapterSweepAffiliate();

    $filtered = app(RunScheduledPayoutSweep::class)->handle(dryRun: true, affiliateId: (string) $affiliate->id);
    $floored = app(RunScheduledPayoutSweep::class)->handle(dryRun: true, minimum: 20000);

    expect($filtered['processed'])->toBe(1)
        ->and($floored['processed'])->toBe(0)
        ->and($floored['skipped'])->toBe(0);
});

it('payout settings page exposes minimum form components', function (): void {
    $page = new ManageAffiliatePayoutSettings('payout-settings-form-test');
    $schema = $page->form(Schema::make(new AdapterOperationsHostComponent)->statePath('data'));

    expect($schema->getComponent('minimum_amount'))->not->toBeNull()
        ->and($schema->getComponent('minimum_amounts_by_currency'))->not->toBeNull();
});

it('payout settings page exposes a save header action', function (): void {
    $actions = adapterPageHeaderActions(ManageAffiliatePayoutSettings::class);
    $names = array_map(fn ($action): string => $action->getName(), $actions);

    expect($names)->toContain('save');
});
