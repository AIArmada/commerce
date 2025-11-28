<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DocTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Name'),

                                TextEntry::make('slug')
                                    ->label('Slug')
                                    ->copyable(),

                                TextEntry::make('doc_type')
                                    ->label('Document Type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('view_name')
                                    ->label('View Name')
                                    ->copyable(),

                                IconEntry::make('is_default')
                                    ->label('Default Template')
                                    ->boolean(),
                            ]),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description')
                            ->columnSpanFull(),
                    ]),

                Section::make('PDF Settings')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('settings.pdf.format')
                                    ->label('Format')
                                    ->placeholder('a4')
                                    ->formatStateUsing(fn (?string $state): string => mb_strtoupper($state ?? 'A4')),

                                TextEntry::make('settings.pdf.orientation')
                                    ->label('Orientation')
                                    ->placeholder('portrait')
                                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'Portrait')),

                                IconEntry::make('settings.pdf.print_background')
                                    ->label('Print Background')
                                    ->boolean()
                                    ->default(true),

                                TextEntry::make('margins')
                                    ->label('Margins (mm)')
                                    ->getStateUsing(function ($record): string {
                                        $settings = $record->settings['pdf']['margin'] ?? [];

                                        return sprintf(
                                            'T:%d R:%d B:%d L:%d',
                                            $settings['top'] ?? 10,
                                            $settings['right'] ?? 10,
                                            $settings['bottom'] ?? 10,
                                            $settings['left'] ?? 10
                                        );
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Custom Settings')
                    ->schema([
                        KeyValueEntry::make('settings.custom')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record): bool => ! empty($record->settings['custom'] ?? [])),

                Section::make('Usage Statistics')
                    ->schema([
                        TextEntry::make('docs_count')
                            ->label('Documents Using This Template')
                            ->getStateUsing(fn ($record): int => $record->docs()->count()),
                    ])
                    ->collapsible(),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
