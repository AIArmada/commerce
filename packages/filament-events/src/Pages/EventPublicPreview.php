<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Pages;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\Event;
use BackedEnum;
use Filament\Infolists;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use UnitEnum;

final class EventPublicPreview extends Page
{
    use VerifiesRecordOwnerContext;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $title = 'Public Preview';

    protected static ?string $slug = 'events/public-preview/{eventId}';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public ?string $eventId = null;

    #[Locked]
    public ?Event $event = null;

    public function mount(?string $eventId = null): void
    {
        // Uniform 404: missing, malformed, and cross-owner ids are
        // indistinguishable, so the URL never leaks event existence across
        // owners. The UUID check also keeps malformed ids from reaching a
        // uuid-typed column comparison (a 500 on PostgreSQL) — events use
        // HasUuids keys.
        if ($eventId === null || $eventId === '' || ! Str::isUuid($eventId)) {
            abort(404);
        }

        $event = OwnerUiScope::apply(Event::query()->with([
            'occurrences', 'locations', 'involvements', 'ticketTypes',
            'materials', 'links', 'mediaRecords', 'updates' => fn ($q) => $q->where('is_pinned', true),
        ]), includeGlobal: false)->find($eventId);

        if (! $event instanceof Event) {
            abort(404);
        }

        $this->eventId = $eventId;
        /* @phpstan-ignore assign.propertyType */
        $this->event = $event;
    }

    protected function ownerGuardedPropName(): string
    {
        return 'event';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->event)
            ->schema([
                Section::make('Event Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')->size('lg'),
                        Infolists\Components\TextEntry::make('summary'),
                        Infolists\Components\TextEntry::make('description'),
                        Infolists\Components\TextEntry::make('status')->badge(),
                        Infolists\Components\TextEntry::make('delivery_mode')->badge(),
                    ]),
                Section::make('Occurrences')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('occurrences')
                            ->schema([
                                Infolists\Components\TextEntry::make('starts_at')->dateTime(),
                                Infolists\Components\TextEntry::make('ends_at')->dateTime(),
                                Infolists\Components\TextEntry::make('status')->badge(),
                            ])->columns(3),
                    ])->collapsible(),
                Section::make('Speakers & Organizers')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('involvements')
                            ->schema([
                                Infolists\Components\TextEntry::make('role_code')->badge(),
                                Infolists\Components\TextEntry::make('prominence')->badge(),
                            ])->columns(2),
                    ])->collapsible(),
                Section::make('Updates & Notices')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('updates')
                            ->schema([
                                Infolists\Components\TextEntry::make('title'),
                                Infolists\Components\TextEntry::make('message'),
                                Infolists\Components\TextEntry::make('severity')->badge(),
                            ]),
                    ])->collapsible(),
                Section::make('Ticket Types')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('ticketTypes')
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('price'),
                                Infolists\Components\TextEntry::make('status')->badge(),
                            ])->columns(3),
                    ])->collapsible(),
            ]);
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getView(): string
    {
        return 'filament-panels::pages.simple';
    }
}
