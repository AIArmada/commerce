<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\FilamentDocs\Actions\SendEmailAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

if (! function_exists('filamentDocs_makeTriggerSchemaLivewire')) {
    function filamentDocs_makeTriggerSchemaLivewire(): LivewireComponent & HasSchemas
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
function filamentDocs_flattenTriggerSchemaComponents(Schema $schema): array
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

it('only offers send-trigger templates in the send email action (M2)', function (): void {
    $doc = Doc::factory()->create(['doc_type' => 'invoice']);

    $send = DocEmailTemplate::query()->create([
        'name' => 'Send Template',
        'slug' => 'send-template',
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Hello',
        'body' => 'Body',
        'is_active' => true,
    ]);

    foreach (['reminder', 'paid', 'overdue'] as $trigger) {
        DocEmailTemplate::query()->create([
            'name' => "Template {$trigger}",
            'slug' => "template-{$trigger}",
            'doc_type' => 'invoice',
            'trigger' => $trigger,
            'subject' => 'Hello',
            'body' => 'Body',
            'is_active' => true,
        ]);
    }

    $action = SendEmailAction::make()->record($doc);
    $schema = $action->getForm(Schema::make(filamentDocs_makeTriggerSchemaLivewire()));

    $select = collect($schema ? filamentDocs_flattenTriggerSchemaComponents($schema) : [])
        ->first(fn ($component) => $component instanceof Select && $component->getName() === 'template_id');

    expect($select)->toBeInstanceOf(Select::class);
    expect($select->getOptions())->toBe([(string) $send->getKey() => 'Send Template']);
});
