<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm;
use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('filamentDocs_makeGlobalsTable')) {
    function filamentDocs_makeGlobalsTable(): Table
    {
        /** @var HasTable $livewire */
        $livewire = Mockery::mock(HasTable::class);

        return Table::make($livewire);
    }
}

if (! function_exists('filamentDocs_makeGlobalsSchemaLivewire')) {
    function filamentDocs_makeGlobalsSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

/**
 * @return array<int, Component|Action|ActionGroup>
 */
function filamentDocs_flattenGlobalsSchemaComponents(Schema $schema): array
{
    $flattened = [];

    $walk = function (array $components) use (&$walk, &$flattened): void {
        foreach ($components as $component) {
            $flattened[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                $walk($component->getChildComponents());
            }
        }
    };

    $walk($schema->getComponents());

    return $flattened;
}

it('eager loads relation columns to avoid N+1 (AUD-B4)', function (): void {
    // Control: a bare query carries no eager loads, so the assertions below
    // only pass because the tables add them.
    expect(Doc::query()->getEagerLoads())->toBe([]);

    $scopedDocs = DocsTable::configure(filamentDocs_makeGlobalsTable())
        ->applyQueryScopes(Doc::query());

    expect($scopedDocs->getEagerLoads())->toHaveKey('template');

    $scopedApprovals = app(ApprovalsRelationManager::class)
        ->table(filamentDocs_makeGlobalsTable())
        ->applyQueryScopes(DocApproval::query());

    expect($scopedApprovals->getEagerLoads())->toHaveKey('requestedBy');
    expect($scopedApprovals->getEagerLoads())->toHaveKey('assignedTo');
});

it('searches templates server-side with a capped result set (AUD-B5)', function (): void {
    for ($i = 0; $i < 60; $i++) {
        DocTemplate::factory()->create([
            'name' => 'Template-' . mb_str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'slug' => 'template-' . $i,
            'doc_type' => 'invoice',
        ]);
    }

    $schema = DocForm::configure(Schema::make(filamentDocs_makeGlobalsSchemaLivewire())->record(null));

    $select = collect(filamentDocs_flattenGlobalsSchemaComponents($schema))
        ->first(fn ($component) => $component instanceof Select && $component->getName() === 'doc_template_id');

    expect($select)->toBeInstanceOf(Select::class);

    expect($select->getSearchResults('Template-0'))->toHaveCount(50);
    expect($select->getSearchResults('NoSuchTemplateName'))->toBe([]);
});
