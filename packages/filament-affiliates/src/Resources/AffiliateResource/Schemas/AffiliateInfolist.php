<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class AffiliateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Affiliate')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->copyable()
                            ->icon(Heroicon::OutlinedIdentification),

                        TextEntry::make('name')
                            ->label('Name'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match (AffiliateStatus::from($state)) {
                                AffiliateStatus::Active => 'success',
                                AffiliateStatus::Pending => 'warning',
                                AffiliateStatus::Paused => 'gray',
                                AffiliateStatus::Disabled => 'danger',
                                default => 'info',
                            })
                            ->formatStateUsing(fn (string $state): string => AffiliateStatus::from($state)->label()),
                    ]),

                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('—'),
                ]),

            Section::make('Contact & Tracking')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('contact_email')->label('Email'),
                        TextEntry::make('website_url')->label('Website'),
                        TextEntry::make('default_voucher_code')->label('Voucher'),
                        TextEntry::make('parent.name')
                            ->label('Parent')
                            ->placeholder('—'),
                    ]),
                ])
                ->collapsed(),

            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('metadata')
                        ->label('Metadata')
                        ->hidden(fn ($state): bool => empty($state ?? [])),
                ])
                ->collapsed(),
        ]);
    }
}
