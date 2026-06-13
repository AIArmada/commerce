<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeatMap;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SeatMapManager extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';

    protected static string | UnitEnum | null $navigationGroup = 'Events';

    protected static ?string $title = 'Seat Maps';

    protected static ?string $slug = 'events/seat-maps';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EventSeatMap::query()
                    ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
                    ->with('event')
            )
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('event.title'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sections_count')->counts('sections')->label('Sections'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->actions([ViewAction::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Seat Map')
                ->form([
                    Select::make('event_id')
                        ->label('Event')
                        ->options(fn (): array => OwnerUiScope::apply(Event::query())
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('name')->required(),
                ])
                ->action(function (array $data): void {
                    OwnerWriteGuard::findOrFailForOwner(Event::class, $data['event_id']);

                    EventSeatMap::query()->create([
                        'event_id' => $data['event_id'],
                        'name' => $data['name'],
                        'status' => 'draft',
                    ]);
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
