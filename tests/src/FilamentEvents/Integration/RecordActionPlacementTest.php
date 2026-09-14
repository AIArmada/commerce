<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use AIArmada\FilamentEvents\Resources\EventResource;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as LivewireComponent;

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

if (! function_exists('recordPlacement_makeSchemaLivewire')) {
    function recordPlacement_makeSchemaLivewire(): LivewireComponent & HasSchemas
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

if (! function_exists('recordPlacement_findComponent')) {
    function recordPlacement_findComponent(Schema $schema, string $name): ?Component
    {
        $walk = function (array $components) use (&$walk, $name): ?Component {
            foreach ($components as $component) {
                if ($component instanceof Component && method_exists($component, 'getName') && $component->getName() === $name) {
                    return $component;
                }

                if (method_exists($component, 'getChildComponents')) {
                    $found = $walk($component->getChildComponents());

                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            return null;
        };

        return $walk($schema->getComponents());
    }
}

if (! function_exists('recordPlacement_slugRule')) {
    function recordPlacement_slugRule(string $resource, string $model): ?Unique
    {
        $schema = $resource::form(Schema::make(recordPlacement_makeSchemaLivewire())->model($model));
        $field = recordPlacement_findComponent($schema, 'slug');

        if (! $field instanceof TextInput) {
            return null;
        }

        $field->model($model);

        foreach ($field->getValidationRules() as $rule) {
            if ($rule instanceof Unique) {
                return $rule;
            }
        }

        return null;
    }
}

it('keeps record lifecycle actions on the row, not the header', function (): void {
    $makeTable = fn (): Table => Table::make(Mockery::mock(HasTable::class));
    $names = fn (array $actions): array => array_map(
        fn (Filament\Actions\Action | ActionGroup $action): string => $action->getName(),
        $actions
    );

    $eventTable = EventResource::table($makeTable());
    expect($names($eventTable->getActions()))->toContain('publish', 'archive', 'cancel')
        ->and($names($eventTable->getHeaderActions()))->not->toContain('publish', 'archive', 'cancel');

    $occurrenceTable = EventOccurrenceResource::table($makeTable());
    expect($names($occurrenceTable->getActions()))->toContain('delay', 'postpone', 'cancel', 'complete')
        ->and($names($occurrenceTable->getHeaderActions()))->not->toContain('delay', 'postpone', 'cancel', 'complete');

    $sessionTable = EventSessionResource::table($makeTable());
    expect($names($sessionTable->getActions()))->toContain('delay', 'postpone', 'cancel', 'complete')
        ->and($names($sessionTable->getHeaderActions()))->not->toContain('delay', 'postpone', 'cancel', 'complete');
});

it('scopes slug uniqueness to the current owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Slug Owner A',
        'email' => 'slug-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Slug Owner B',
        'email' => 'slug-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): Event => Event::factory()->create(['slug' => 'shared-slug']));

    $passesUnderB = OwnerContext::withOwner($ownerB, function (): bool {
        $rule = recordPlacement_slugRule(EventResource::class, Event::class);

        expect($rule)->toBeInstanceOf(Unique::class);

        return Validator::make(['slug' => 'shared-slug'], ['slug' => $rule])->passes();
    });

    expect($passesUnderB)->toBeTrue();

    $failsUnderA = OwnerContext::withOwner($ownerA, function (): bool {
        $rule = recordPlacement_slugRule(EventResource::class, Event::class);

        return Validator::make(['slug' => 'shared-slug'], ['slug' => $rule])->fails();
    });

    expect($failsUnderA)->toBeTrue();
});

it('keeps occurrence and session slugs globally unique', function (): void {
    // Occurrences and sessions intentionally carry no owner columns
    // (ScopesByEventOwner resolves ownership through the parent event), so
    // per-owner slug scoping is not applicable there: uniqueness stays
    // global. Owner-style wheres must never be added — on SQLite they
    // silently match nothing (quoted unknown identifiers degrade to string
    // literals), which would neuter the rule instead of scoping it.
    $ownerA = User::query()->create([
        'name' => 'Slug Owner C',
        'email' => 'slug-owner-c-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Slug Owner D',
        'email' => 'slug-owner-d-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function (): void {
        $event = Event::factory()->create(['slug' => 'slug-scope-event']);
        EventOccurrence::factory()->create(['event_id' => $event->id, 'slug' => 'shared-child-slug']);
        EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $event->occurrences()->first()->id,
            'slug' => 'shared-child-slug',
        ]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        $occurrenceRule = recordPlacement_slugRule(EventOccurrenceResource::class, EventOccurrence::class);
        $sessionRule = recordPlacement_slugRule(EventSessionResource::class, EventSession::class);

        expect($occurrenceRule)->toBeInstanceOf(Unique::class)
            ->and($sessionRule)->toBeInstanceOf(Unique::class)
            ->and((string) $occurrenceRule)->not->toContain('owner_type')
            ->and((string) $sessionRule)->not->toContain('owner_type')
            ->and(Validator::make(['slug' => 'shared-child-slug'], ['slug' => $occurrenceRule])->fails())->toBeTrue()
            ->and(Validator::make(['slug' => 'shared-child-slug'], ['slug' => $sessionRule])->fails())->toBeTrue();
    });
});
