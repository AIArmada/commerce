<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

if (! function_exists('filamentDocs_makeEmailTemplateSchemaLivewire')) {
    function filamentDocs_makeEmailTemplateSchemaLivewire(): LivewireComponent & HasSchemas
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
function filamentDocs_flattenEmailTemplateSchemaComponents(Schema $schema): array
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

it('includes the due soon trigger in the email template resource form', function (): void {
    $schema = DocEmailTemplateResource::form(Schema::make(filamentDocs_makeEmailTemplateSchemaLivewire()));

    $triggerSelect = collect(filamentDocs_flattenEmailTemplateSchemaComponents($schema))
        ->first(fn ($component) => $component instanceof Select && $component->getName() === 'trigger');

    expect($triggerSelect)->toBeInstanceOf(Select::class);
    expect($triggerSelect->getOptions())->toHaveKey('due_soon');
    expect($triggerSelect->getOptions()['due_soon'])->toBe('Upcoming due date reminder');
});