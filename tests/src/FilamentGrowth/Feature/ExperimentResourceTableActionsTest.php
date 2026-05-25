<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

function filamentGrowthTableToggleUser(): User
{
    return User::query()->create([
        'name' => 'Growth Toggle User ' . Str::random(6),
        'email' => 'growth-toggle-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function filamentGrowthTableToggleBindOwner(User $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function filamentGrowthTableToggleLivewire(): HasTable
{
    $livewire = Mockery::mock(HasTable::class);
    $livewire
        ->shouldReceive('getTableRecordKey')
        ->andReturnUsing(static fn (Model $record): string => (string) $record->getKey());

    return $livewire;
}

function filamentGrowthTableToggleCreateExperiment(User $owner, ExperimentStatus $status): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($owner, $status): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Growth Toggle Property ' . Str::random(6),
            'slug' => 'growth-toggle-property-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => 'Growth Toggle Experiment ' . Str::random(6),
            'slug' => 'growth-toggle-experiment-' . Str::lower(Str::random(8)),
            'status' => $status->value,
        ]);

        return $experiment;
    });
}

it('registers an inline running toggle that updates experiment status', function (): void {
    filament()->setCurrentPanel('admin');

    $owner = filamentGrowthTableToggleUser();

    filamentGrowthTableToggleBindOwner($owner);
    $this->actingAs($owner);

    $experiment = filamentGrowthTableToggleCreateExperiment($owner, ExperimentStatus::Paused);

    $livewire = filamentGrowthTableToggleLivewire();

    $table = ExperimentResource::table(Table::make($livewire));
    $column = $table->getColumn('is_running');
    $statusColumn = $table->getColumn('status');

    expect($column)->toBeInstanceOf(ToggleColumn::class)
        ->and($statusColumn?->getGroup())->toBeInstanceOf(ColumnGroup::class)
        ->and($statusColumn?->getGroup()?->getLabel())->toBe('Lifecycle')
        ->and($column?->getGroup())->toBeInstanceOf(ColumnGroup::class)
        ->and($column?->getGroup()?->getLabel())->toBe('Lifecycle')
        ->and($table->getAction('activate'))->not->toBeNull()
        ->and($table->getAction('pause'))->not->toBeNull();

    /** @var ToggleColumn $column */
    $column->record($experiment);

    expect($column->getState())->toBeFalse();

    $column->updateState(true);

    expect($experiment->refresh()->status)->toBe(ExperimentStatus::Active);

    $column->record($experiment->fresh());
    $column->updateState(false);

    expect($experiment->refresh()->status)->toBe(ExperimentStatus::Paused);
});

it('keeps the inline running toggle disabled for concluded experiments', function (): void {
    filament()->setCurrentPanel('admin');

    $owner = filamentGrowthTableToggleUser();

    filamentGrowthTableToggleBindOwner($owner);
    $this->actingAs($owner);

    $experiment = filamentGrowthTableToggleCreateExperiment($owner, ExperimentStatus::Concluded);

    $column = ExperimentResource::table(Table::make(filamentGrowthTableToggleLivewire()))
        ->getColumn('is_running');

    expect($column)->toBeInstanceOf(ToggleColumn::class);

    /** @var ToggleColumn $column */
    $column->record($experiment);

    expect($column->isDisabled())->toBeTrue()
        ->and($column->getState())->toBeFalse();

    $column->updateState(true);

    expect($experiment->refresh()->status)->toBe(ExperimentStatus::Concluded);
});
