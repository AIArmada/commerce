<?php

declare(strict_types=1);

use AIArmada\FilamentEvents\Contracts\EventFormExtension;
use AIArmada\FilamentEvents\Extensions\DefaultEventMediaExtension;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentEventsExtension_makeSchemaLivewire')) {
    function filamentEventsExtension_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            use InteractsWithSchemas;

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

if (! function_exists('filamentEventsExtension_flattenComponents')) {
    function filamentEventsExtension_flattenComponents(array $components): array
    {
        $all = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            $all[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                $all = [...$all, ...filamentEventsExtension_flattenComponents($component->getChildComponents())];
            }
        }

        return $all;
    }
}

it('ships as the default event form extension', function (): void {
    expect(config('filament-events.resources.event_form_extensions'))
        ->toContain(DefaultEventMediaExtension::class)
        ->and(app(DefaultEventMediaExtension::class))->toBeInstanceOf(EventFormExtension::class);
});

it('renders nothing when the resolved event model does not support media', function (): void {
    config()->set('events.models.event', stdClass::class);

    expect((new DefaultEventMediaExtension)->components())->toBe([]);
});

it('adds cover, poster, and gallery uploads to the event form', function (): void {
    $livewire = filamentEventsExtension_makeSchemaLivewire();
    $components = collect(filamentEventsExtension_flattenComponents(
        EventResource::form(Schema::make($livewire))->getComponents()
    ));

    $uploads = $components
        ->filter(static fn (object $component): bool => $component instanceof SpatieMediaLibraryFileUpload
            && in_array($component->getName(), ['cover', 'poster', 'gallery'], true))
        ->values();

    expect($uploads->map(static fn (SpatieMediaLibraryFileUpload $upload): ?string => $upload->getName())->all())
        ->toBe(['cover', 'poster', 'gallery'])
        ->and($uploads->map(static fn (SpatieMediaLibraryFileUpload $upload): ?string => $upload->getCollection())->all())
        ->toBe(['cover', 'poster', 'gallery']);
});
