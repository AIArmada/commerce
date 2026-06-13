<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\Schemas;

use AIArmada\Affiliates\Enums\ProgramVisibility;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AffiliateProgramInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('name')
                            ->label('Name'),

                        TextEntry::make('slug')
                            ->label('Slug')
                            ->badge(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ]),

            Section::make('Commission & Access')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('commission_type')
                            ->label('Commission Type')
                            ->badge(),

                        TextEntry::make('default_commission_rate_basis_points')
                            ->label('Default Commission')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : ($state / 100) . '%'),

                        TextEntry::make('cookie_lifetime_days')
                            ->label('Cookie Lifetime')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state . ' days'),

                        TextEntry::make('visibility')
                            ->label('Visibility')
                            ->badge()
                            ->color(fn (ProgramVisibility $state): string => $state->color()),

                        IconEntry::make('requires_approval')
                            ->label('Requires Approval')
                            ->boolean(),

                        TextEntry::make('terms_url')
                            ->label('Terms URL')
                            ->placeholder('—')
                            ->url(fn (?string $state): ?string => $state),
                    ]),
                ]),

            Section::make('Schedule')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('starts_at')
                            ->label('Starts At')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('ends_at')
                            ->label('Ends At')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                ]),
        ]);
    }
}
