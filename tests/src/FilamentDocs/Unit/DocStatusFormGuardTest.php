<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

if (! function_exists('filamentDocs_makeStatusGuardSchemaLivewire')) {
    function filamentDocs_makeStatusGuardSchemaLivewire(): LivewireComponent & HasSchemas
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
function filamentDocs_flattenStatusGuardSchemaComponents(Schema $schema): array
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

function filamentDocs_docFormSelect(?Doc $record, string $name): Select
{
    $schema = DocForm::configure(Schema::make(filamentDocs_makeStatusGuardSchemaLivewire())->record($record));

    $select = collect(filamentDocs_flattenStatusGuardSchemaComponents($schema))
        ->first(fn ($component) => $component instanceof Select && $component->getName() === $name);

    expect($select)->toBeInstanceOf(Select::class);

    return $select;
}

it('lists every status for new documents (H2)', function (): void {
    $select = filamentDocs_docFormSelect(null, 'status');

    expect(array_keys($select->getOptions()))->toEqualCanonicalizing(array_keys(DocStatus::options()));
});

it('restricts status options to valid transitions on edit (H2)', function (): void {
    $paidDoc = Doc::factory()->create(['status' => Paid::class]);

    $options = filamentDocs_docFormSelect($paidDoc, 'status')->getOptions();

    expect(array_keys($options))->toEqualCanonicalizing([
        DocStatus::normalize(Paid::class),
        'refunded',
    ]);

    $draftDoc = Doc::factory()->create(['status' => Draft::class]);

    $draftOptions = filamentDocs_docFormSelect($draftDoc, 'status')->getOptions();

    expect(array_keys($draftOptions))->toEqualCanonicalizing([
        'draft',
        'pending',
        'sent',
        'paid',
        'overdue',
        'cancelled',
    ]);
    expect($draftOptions)->not()->toHaveKey('partially_paid');
    expect($draftOptions)->not()->toHaveKey('refunded');
});

it('constrains currency, tax rate, and line items (L5)', function (): void {
    $schema = DocForm::configure(Schema::make(filamentDocs_makeStatusGuardSchemaLivewire())->record(null));

    $components = collect(filamentDocs_flattenStatusGuardSchemaComponents($schema));

    /** @var TextInput $currency */
    $currency = $components->first(fn ($component) => $component instanceof TextInput && $component->getName() === 'currency');
    expect($currency)->toBeInstanceOf(TextInput::class);
    expect($currency->getValidationRules())->not()->toBeEmpty();

    /** @var TextInput $taxRate */
    $taxRate = $components->first(fn ($component) => $component instanceof TextInput && $component->getName() === 'tax_rate');
    expect($taxRate)->toBeInstanceOf(TextInput::class);
    expect($taxRate->getMaxValue())->toEqual(100);
    expect($taxRate->getMinValue())->toEqual(0);
});
