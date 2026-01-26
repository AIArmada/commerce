<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas;

use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;
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
                            ->color(function (AffiliateStatus | string $state): string {
                                $status = AffiliateStatus::fromString($state);

                                return match (true) {
                                    $status instanceof Draft => 'gray',
                                    $status instanceof Active => 'success',
                                    $status instanceof Pending => 'warning',
                                    $status instanceof Paused => 'gray',
                                    $status instanceof Disabled => 'danger',
                                    default => 'gray',
                                };
                            })
                            ->formatStateUsing(fn (AffiliateStatus | string $state): string => AffiliateStatus::fromString($state)->label()),
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
