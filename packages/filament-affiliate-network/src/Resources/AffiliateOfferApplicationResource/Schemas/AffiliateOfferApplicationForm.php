<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class AffiliateOfferApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application Details')
                    ->schema([
                        Select::make('offer_id')
                            ->label('Offer')
                            // Admin resource: cross-tenant — show all offers regardless of owner scope.
                            ->relationship('offer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope('owner_via_site'))
                            ->required()
                            ->disabled(),

                        Select::make('affiliate_id')
                            ->label('Affiliate')
                            ->relationship('affiliate', 'code')
                            ->required()
                            ->disabled(),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'revoked' => 'Revoked',
                            ])
                            ->required(),

                        Textarea::make('reason')
                            ->label('Application Reason')
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('rejection_reason')
                            ->label('Rejection/Revocation Reason')
                            ->columnSpanFull(),

                        TextInput::make('reviewed_by')
                            ->disabled(),

                        DateTimePicker::make('reviewed_at')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }
}
