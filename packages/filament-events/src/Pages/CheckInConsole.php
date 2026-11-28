<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\Events\Contracts\EventAdministrativeCheckIn;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Ticketing\Models\Pass;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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

final class CheckInConsole extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Check-In Console';

    protected static ?string $slug = 'events/check-in';

    public ?string $passOrRegistration = '';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getFilteredQuery())
            ->columns([
                TextColumn::make('pass_no')->label('Pass #')->searchable()->copyable(),
                TextColumn::make('registration.registration_no')->label('Registration #')->copyable(),
                TextColumn::make('registration.registrant_type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('issued_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->actions([
                Action::make('checkIn')
                    ->label('Check In')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Pass $record): void {
                        app(EventAdministrativeCheckIn::class)->checkInPass((string) $record->getKey(), Filament::auth()->user());
                    })
                    ->visible(fn (Pass $record) => $record->isValid()),
            ]);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    private function getFilteredQuery(): Builder
    {
        $query = Pass::query()
            ->with('registration')
            ->where('registration_type', (new (ModelResolver::registrationClass()))->getMorphClass())
            ->whereHasMorph(
                'registration',
                [ModelResolver::registrationClass()],
                fn (Builder $registrationQuery): Builder => $registrationQuery
                    ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            );

        if ($this->passOrRegistration) {
            $pattern = LikeSearch::contains($this->passOrRegistration);

            $query->where(function (Builder $q) use ($pattern): void {
                LikeSearch::whereLike($q, 'pass_no', $pattern);
                $q->orWhereHas(
                    'registration',
                    fn (Builder $r) => LikeSearch::whereLike($r, 'registration_no', $pattern)
                );
            });
        }

        return $query->latest();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('search')
                ->label('Search')
                ->form([
                    TextInput::make('query')
                        ->maxLength(255)
                        ->label('Pass Number or Registration #')
                        ->placeholder('e.g. PASS-ABC123 or REG-001')
                        ->autocomplete(false),
                ])
                ->action(function (array $data): void {
                    $this->passOrRegistration = $data['query'];
                }),
            Action::make('walkIn')
                ->label('Walk-In Check-In')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->form([
                    Select::make('event_id')
                        ->label('Event')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            $query = OwnerUiScope::apply(ModelResolver::eventClass()::query(), includeGlobal: false);
                            LikeSearch::whereLike($query, 'title', LikeSearch::contains($search));

                            return $query
                                ->orderBy('title')
                                ->limit(50)
                                ->pluck('title', 'id')
                                ->all();
                        })
                        ->getOptionLabelUsing(fn (mixed $value): ?string => OwnerUiScope::apply(ModelResolver::eventClass()::query(), includeGlobal: false)
                            ->whereKey($value)
                            ->value('title'))
                        ->required(),
                    TextInput::make('attendee_name')->label('Name')->maxLength(255),
                    TextInput::make('attendee_email')->label('Email')->email()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    app(EventAdministrativeCheckIn::class)->checkInWalkIn(
                        $data['event_id'],
                        Filament::auth()->user(),
                        $data['attendee_name'] ?? null,
                        $data['attendee_email'] ?? null,
                    );
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
