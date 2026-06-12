<?php
declare(strict_types=1);
namespace AIArmada\FilamentEvents\Pages;

use BackedEnum;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\Event;
use Filament\Pages\Page;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class EventPublicPreview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';
    protected static string|\UnitEnum|null $navigationGroup = 'Events';
    protected static ?string $title = 'Public Preview';
    protected static ?string $slug = 'events/public-preview';
    protected static bool $shouldRegisterNavigation = false;

    public ?string $eventId = null;
    public ?Event $event = null;

    public function mount(?string $event = null): void
    {
        if ($event) {
            $this->eventId = $event;
            $this->event = OwnerUiScope::apply(Event::query()->with([
                'occurrences', 'locations', 'involvements', 'ticketTypes',
                'materials', 'links', 'media', 'updates' => fn ($q) => $q->where('is_pinned', true),
            ]), includeGlobal: false)->find($event);
        }
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
                        Infolists\Components\TextEntry::make('description')->html(),
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

    public function getView(): string
    {
        return 'filament-pages::simple-page';
    }
}
