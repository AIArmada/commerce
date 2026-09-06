<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
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
                        TextInput::make('rate_base_bp')
                            ->label('Base rate (basis points)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Percentage in basis points (1000 = 10%). Leave empty for fixed-only offers.'),

                        TextInput::make('rate_fixed_minor')
                            ->label('Fixed amount (minor units)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Fixed payout per conversion. Takes precedence for display when set.'),

                        TextInput::make('currency')
                            ->maxLength(3)
                            ->placeholder('USD'),

                        TextInput::make('cookie_days')
                            ->label('Cookie Duration (days)')
                            ->numeric()
                            ->nullable()
                            ->placeholder('30'),

                        Select::make('rate_source')
                            ->label('Rate source')
                            ->options([
                                'synced' => 'Synced from catalog',
                                'manual' => 'Manually managed',
                            ])
                            ->default('synced')
                            ->helperText('Editing any rate field flips this to manual; sync then holds rates back until you switch it back.'),
                    ])
                    ->columns(4),

                Section::make('Settings')
                    ->schema([
                        Select::make('status')
                            ->options([
                                OfferStatus::Draft->value => 'Draft',
                                OfferStatus::Published->value => 'Published',
                                OfferStatus::Archived->value => 'Archived',
                            ])
                            ->required()
                            ->default(OfferStatus::Draft->value),

                        Select::make('visibility')
                            ->options([
                                OfferVisibility::Public->value => 'Public',
                                OfferVisibility::Private->value => 'Private',
                                OfferVisibility::Unlisted->value => 'Unlisted',
                            ])
                            ->default(OfferVisibility::Public->value)
                            ->helperText('Controls marketplace visibility'),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

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
