<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\CompanyStatementResource\Schemas;

use AIArmada\Chip\Models\CompanyStatement;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class CompanyStatementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Statement Information')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('id')
                                ->label('Statement ID')
                                ->copyable()
                                ->weight(FontWeight::Bold),

                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state): string => ucfirst($state ?? 'unknown'))
                                ->color(fn (CompanyStatement $record): string => $record->statusColor()),

                            IconEntry::make('is_test')
                                ->label('Test Mode')
                                ->boolean()
                                ->trueIcon(Heroicon::OutlinedBeaker)
                                ->falseIcon(Heroicon::OutlinedCheckBadge)
                                ->trueColor('warning')
                                ->falseColor('success'),
                        ]),
                ]),

            Section::make('Statement Period')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('began_on')
                                ->label('Period Start')
                                ->formatStateUsing(fn (CompanyStatement $record): string => $record->beganOn?->format('F j, Y') ?? 'Not set'),

                            TextEntry::make('finished_on')
                                ->label('Period End')
                                ->formatStateUsing(fn (CompanyStatement $record): string => $record->finishedOn?->format('F j, Y') ?? 'Not set'),
                        ]),
                ]),

            Section::make('Timestamps')
                ->icon(Heroicon::OutlinedClock)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_on')
                                ->label('Requested At')
                                ->formatStateUsing(fn (CompanyStatement $record): string => $record->createdOn?->format('F j, Y g:i A') ?? 'Unknown'),

                            TextEntry::make('updated_on')
                                ->label('Last Updated')
                                ->formatStateUsing(fn (CompanyStatement $record): string => $record->updatedOn?->format('F j, Y g:i A') ?? 'Unknown'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created (Local)')
                                ->dateTime(),

                            TextEntry::make('updated_at')
                                ->label('Updated (Local)')
                                ->dateTime(),
                        ]),
                ]),
        ]);
    }
}
