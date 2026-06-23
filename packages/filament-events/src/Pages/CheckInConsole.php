<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPass;
use BackedEnum;
use Filament\Actions\Action;
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

    public ?string $search = '';

    public ?string $passOrRegistration = '';

    public array $checkInResult = [];

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
                    ->action(function (EventPass $record): void {
                        app(EventCheckInService::class)->checkIn([
                            'event_id' => $record->event_id,
                            'event_occurrence_id' => $record->event_occurrence_id,
                            'event_pass_id' => $record->id,
                            'event_registration_id' => $record->event_registration_id,
                            'attendance_type' => 'registered',
                            'check_in_source' => 'admin',
                        ]);
                    })
                    ->visible(fn (EventPass $record) => $record->status === 'issued' || $record->status === 'active'),
            ]);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    private function getFilteredQuery(): Builder
    {
        $query = EventPass::query()
            ->with('registration')
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false));

        if ($this->passOrRegistration) {
            $query->where(function (Builder $q): void {
                $q->where('pass_no', 'like', "%{$this->passOrRegistration}%")
                    ->orWhereHas(
                        'registration',
                        fn (Builder $r) => $r->where('registration_no', 'like', "%{$this->passOrRegistration}%")
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
                        ->label('Pass Number, Registration #, or Name')
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
                        ->options(fn (): array => OwnerUiScope::apply(Event::query(), includeGlobal: false)
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('attendee_name')->label('Name'),
                    TextInput::make('attendee_email')->label('Email'),
                ])
                ->action(function (array $data): void {
                    OwnerWriteGuard::findOrFailForOwner(Event::class, $data['event_id']);

                    app(EventCheckInService::class)->checkIn([
                        'event_id' => $data['event_id'],
                        'attendance_type' => 'walk_in',
                        'check_in_source' => 'admin',
                        'metadata' => ['name' => $data['attendee_name'] ?? null, 'email' => $data['attendee_email'] ?? null],
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
