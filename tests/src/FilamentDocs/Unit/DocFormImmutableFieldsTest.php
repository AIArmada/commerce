<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

if (! function_exists('filamentDocs_makeImmutableFieldsLivewire')) {
    function filamentDocs_makeImmutableFieldsLivewire(): LivewireComponent & HasSchemas
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

if (! function_exists('filamentDocs_flattenImmutableFieldsComponents')) {
    /**
     * @return array<int, Component|Action|ActionGroup>
     */
    function filamentDocs_flattenImmutableFieldsComponents(Schema $schema): array
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
}

if (! function_exists('filamentDocs_findImmutableFieldComponent')) {
    function filamentDocs_findImmutableFieldComponent(?Doc $record, string $operation, string $name): Component
    {
        $schema = DocForm::configure(
            Schema::make(filamentDocs_makeImmutableFieldsLivewire())->operation($operation)->record($record)
        );

        $component = collect(filamentDocs_flattenImmutableFieldsComponents($schema))
            ->first(fn ($candidate) => $candidate instanceof Component
                && method_exists($candidate, 'getName')
                && $candidate->getName() === $name);

        expect($component)->toBeInstanceOf(Component::class);

        return $component;
    }
}

it('disables immutable document fields when editing', function (): void {
    $doc = Doc::factory()->create();

    $enabledOnEdit = [];

    foreach (['doc_number', 'doc_type', 'subtotal_minor', 'tax_amount_minor', 'total_minor'] as $name) {
        if (! filamentDocs_findImmutableFieldComponent($doc, 'edit', $name)->isDisabled()) {
            $enabledOnEdit[] = $name;
        }
    }

    expect($enabledOnEdit)->toBe([]);

    expect(filamentDocs_findImmutableFieldComponent($doc, 'edit', 'discount_amount_minor')->isDisabled())
        ->toBeFalse();
});

it('keeps immutable document fields editable when creating', function (): void {
    $disabledOnCreate = [];

    foreach (['doc_number', 'doc_type', 'subtotal_minor', 'tax_amount_minor', 'total_minor'] as $name) {
        if (filamentDocs_findImmutableFieldComponent(null, 'create', $name)->isDisabled()) {
            $disabledOnCreate[] = $name;
        }
    }

    expect($disabledOnCreate)->toBe([]);
});
