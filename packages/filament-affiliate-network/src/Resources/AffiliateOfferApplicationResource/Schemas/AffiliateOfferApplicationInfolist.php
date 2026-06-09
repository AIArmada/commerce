<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Schemas;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AffiliateOfferApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application Details')
                    ->schema([
                        TextEntry::make('offer.name')
                            ->label('Offer'),

                        TextEntry::make('affiliate.code')
                            ->label('Affiliate'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                AffiliateOfferApplication::STATUS_APPROVED => 'success',
                                AffiliateOfferApplication::STATUS_PENDING => 'warning',
                                default => 'danger',
                            }),

                        TextEntry::make('reason')
                            ->label('Application Reason')
                            ->placeholder('—'),

                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('—'),

                        TextEntry::make('reviewed_by')
                            ->label('Reviewed By'),

                        TextEntry::make('reviewed_at')
                            ->label('Reviewed At')
                            ->dateTime(),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
