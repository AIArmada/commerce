<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Support\AffiliateNetworkOptionsProvider;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class AffiliateOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Offer Details')
                    ->schema([
                        Select::make('site_id')
                            ->label('Site')
                            ->options(fn (): array => AffiliateNetworkOptionsProvider::verifiedSiteOptions())
                            ->required()
                            ->searchable(),

                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn (): array => AffiliateNetworkOptionsProvider::activeCategoryOptions())
                            ->searchable()
                            ->nullable(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->maxLength(2000)
                            ->columnSpanFull(),

                        Textarea::make('terms')
                            ->label('Terms & Conditions')
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Commission')
                    ->schema([
                        Select::make('commission_type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed Amount',
                            ])
                            ->required()
                            ->default('percentage'),

                        TextInput::make('commission_rate')
                            ->numeric()
                            ->required()
                            ->default(1000)
                            ->helperText('In basis points (1000 = 10%) or minor units for fixed'),

                        TextInput::make('currency')
                            ->maxLength(3)
                            ->placeholder('USD'),

                        TextInput::make('cookie_days')
                            ->label('Cookie Duration (days)')
                            ->numeric()
                            ->nullable()
                            ->placeholder('30'),
                    ])
                    ->columns(4),

                Section::make('Settings')
                    ->schema([
                        Select::make('status')
                            ->options([
                                AffiliateOffer::STATUS_DRAFT => 'Draft',
                                AffiliateOffer::STATUS_PENDING => 'Pending Review',
                                AffiliateOffer::STATUS_ACTIVE => 'Active',
                                AffiliateOffer::STATUS_PAUSED => 'Paused',
                                AffiliateOffer::STATUS_EXPIRED => 'Expired',
                                AffiliateOffer::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required()
                            ->default(AffiliateOffer::STATUS_DRAFT),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Toggle::make('is_public')
                            ->label('Public')
                            ->default(true)
                            ->helperText('Visible in marketplace'),

                        Toggle::make('requires_approval')
                            ->label('Requires Approval')
                            ->default(true)
                            ->helperText('Affiliates must apply to promote'),

                        TextInput::make('landing_url')
                            ->label('Landing Page URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        DateTimePicker::make('starts_at')
                            ->nullable(),

                        DateTimePicker::make('ends_at')
                            ->nullable(),
                    ])
                    ->columns(4),

                Section::make('Advanced')
                    ->schema([
                        KeyValue::make('restrictions')
                            ->nullable()
                            ->columnSpanFull(),

                        KeyValue::make('metadata')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }
}
