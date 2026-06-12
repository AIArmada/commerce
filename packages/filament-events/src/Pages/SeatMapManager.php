<?php
declare(strict_types=1);
namespace AIArmada\FilamentEvents\Pages;

use BackedEnum;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeatMap;
use AIArmada\Events\Models\EventSeatSection;
use AIArmada\Events\Models\EventSeat;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SeatMapManager extends Page
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static string|\UnitEnum|null $navigationGroup = 'Events';
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
            ->actions([\Filament\Actions\ViewAction::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Seat Map')
                ->form([
                    Select::make('event_id')
                        ->relationship('event', 'title', modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false))
                        ->required(),
                    TextInput::make('name')->required(),
                ])
                ->action(function (array $data) {
                    OwnerWriteGuard::findOrFailForOwner(Event::class, $data['event_id']);

                    EventSeatMap::query()->create([
                        'event_id' => $data['event_id'],
                        'name' => $data['name'],
                        'status' => 'draft',
                    ]);
                }),
        ];
    }

    public function getView(): string
    {
        return 'filament-pages::simple-page';
    }
}
