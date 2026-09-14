<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

final class AffiliateOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Offer Details')
                    ->schema([
                        // Admin form: cross-tenant — relationship selects search server-side
                        // (no unbounded preload) with explicit global scope bypass.
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->withoutGlobalScope(OwnerScope::class)
                                ->where('status', AffiliateSite::STATUS_VERIFIED))
                            ->required()
                            ->searchable(),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->withoutGlobalScope(OwnerScope::class)
                                ->where('is_active', true))
                            ->searchable()
                            ->nullable(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('site_id', (string) $get('site_id')),
                            ),

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
                        // Integer money/count fields use explicit rules instead of
                        // ->integer(): the integer state cast would silently truncate
                        // decimals (10.5 becomes 10) before validation runs, while the
                        // domain rejects them — the form must reject them too.
                        TextInput::make('rate_base_bp')
                            ->label('Base rate (basis points)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable()
                            ->helperText('Percentage in basis points (1000 = 10%). Leave empty for fixed-only offers.'),

                        TextInput::make('rate_fixed_minor')
                            ->label('Fixed amount (minor units)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable()
                            ->helperText('Fixed payout per conversion. Takes precedence for display when set.'),

                        TextInput::make('currency')
                            ->length(3)
                            ->alpha()
                            ->nullable()
                            ->placeholder('USD'),

                        TextInput::make('cookie_days')
                            ->label('Cookie Duration (days)')
                            ->rule('integer')
                            ->minValue(0)
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
                            ->nullable()
                            ->afterOrEqual('starts_at'),
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
