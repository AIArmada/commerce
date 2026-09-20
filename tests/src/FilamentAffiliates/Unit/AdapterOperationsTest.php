<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Services\ProgramCatalogService;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Pages\ViewAffiliateProgram;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
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
