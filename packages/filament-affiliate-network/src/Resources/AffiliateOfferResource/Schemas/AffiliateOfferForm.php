<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name', modifyQueryUsing: self::siteOptions(...))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('category_id', null))
                            ->searchable(),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name', modifyQueryUsing: self::categoryOptions(...))
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
                            ->placeholder('MYR'),

                        TextInput::make('cookie_days')
                            ->label('Cookie Duration (days)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable()
                            ->placeholder('30'),

                        Select::make('source')
                            ->label('Source')
                            ->options(self::sourceOptions())
                            ->default(OfferSource::Manual->value)
                            ->helperText('Editing any rate field flips this to manual; sync then holds rates back until you switch it back.'),

                        TextInput::make('network_fee_bp')
                            ->label('Network fee (bp)')
                            ->rule('integer')
                            ->minValue(0)
                            ->maxValue(10000)
                            ->nullable()
                            ->placeholder('Default')
                            ->helperText('Marketplace take-rate in basis points. Empty uses the configured default.'),

                        Repeater::make('volume_tiers')
                            ->label('Volume tiers')
                            ->schema([
                                TextInput::make('min_volume_minor')
                                    ->label('Min volume (minor)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('rate_bp')
                                    ->label('Rate (bp)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->required(),
                                Select::make('currency')
                                    ->options([
                                        'USD' => 'USD',
                                        'MYR' => 'MYR',
                                        'SGD' => 'SGD',
                                        'IDR' => 'IDR',
                                    ])
                                    ->helperText('Defaults to the offer currency.'),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->collapsible()
                            ->defaultItems(0)
                            ->helperText('Higher-volume affiliates earn these rates once they clear each floor.'),
                    ])
                    ->columns(4),

                Section::make('Caps & Locking')
                    ->schema([
                        TextInput::make('locking_days')
                            ->label('Locking days')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('daily_conversion_cap')
                            ->label('Daily conversion cap')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('monthly_conversion_cap')
                            ->label('Monthly conversion cap')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('total_conversion_cap')
                            ->label('Total conversion cap')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('per_affiliate_conversion_cap')
                            ->label('Per-affiliate conversion cap')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('monthly_payout_cap_minor')
                            ->label('Monthly payout cap (minor)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('total_payout_cap_minor')
                            ->label('Total payout cap (minor)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),
                    ])
                    ->columns(4),

                Section::make('Settings')
                    ->schema([
                        Select::make('status')
                            ->options(self::statusOptions())
                            ->required()
                            ->default(OfferStatus::Draft->value),

                        Select::make('visibility')
                            ->options(self::visibilityOptions())
                            ->default(OfferVisibility::Public->value)
                            ->helperText('Controls marketplace visibility'),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Toggle::make('requires_approval')
                            ->label('Requires Approval')
                            ->default(true)
                            ->helperText('Affiliates must apply to promote'),

                        Toggle::make('allow_deep_linking')
                            ->label('Allow deep linking')
                            ->default(true),

                        TextInput::make('landing_url')
                            ->label('Landing Page URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('fallback_url')
                            ->label('Fallback URL')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull()
                            ->helperText('Sent to the redirect gate when the offer is no longer available.'),

                        TextInput::make('fallback_offer_id')
                            ->label('Fallback offer ID')
                            ->maxLength(36)
                            ->nullable(),

                        DateTimePicker::make('starts_at')
                            ->nullable(),

                        DateTimePicker::make('ends_at')
                            ->nullable()
                            ->afterOrEqual('starts_at'),
                    ])
                    ->columns(4),

                Section::make('Advanced')
                    ->schema([
                        KeyValue::make('event_rates')
                            ->label('Event rates')
                            ->nullable()
                            ->columnSpanFull(),

                        KeyValue::make('active_promotions')
                            ->label('Active promotions')
                            ->nullable()
                            ->columnSpanFull(),

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

    /**
     * @return array<string, string>
     */
    private static function sourceOptions(): array
    {
        $options = [];

        foreach (OfferSource::cases() as $source) {
            $options[$source->value] = $source->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (OfferStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function visibilityOptions(): array
    {
        $options = [];

        foreach (OfferVisibility::cases() as $visibility) {
            $options[$visibility->value] = $visibility->label();
        }

        return $options;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private static function siteOptions(Builder $query, ?AffiliateOffer $record): Builder
    {
        $query->withoutGlobalScope(OwnerScope::class)
            ->where(function (Builder $query) use ($record): void {
                $query->where('status', SiteStatus::Verified->value)->whereNotNull('verified_at');

                if ($record !== null) {
                    $query->orWhereKey($record->site_id);
                }
            });

        if ($record === null || ! config('affiliate-network.owner.enabled', false)) {
            return $query;
        }

        $site = app(SitesApi::class)->findByIdUnscoped((string) $record->site_id);
        $owner = $site instanceof SiteRecord
            ? OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId)
            : null;

        return OwnerQuery::applyToEloquentBuilder($query, $owner);
    }

    /**
     * @param  Builder<AffiliateOfferCategory>  $query
     * @return Builder<AffiliateOfferCategory>
     */
    private static function categoryOptions(Builder $query, Get $get): Builder
    {
        $query->withoutGlobalScope(OwnerScope::class)->where('is_active', true);

        if (! config('affiliate-network.owner.enabled', false)) {
            return $query;
        }

        $siteId = $get('site_id');
        $site = is_string($siteId) && $siteId !== ''
            ? app(SitesApi::class)->findByIdUnscoped($siteId)
            : null;

        if (! $site instanceof SiteRecord) {
            return $query->whereRaw('1 = 0');
        }

        $owner = OwnerContext::fromTypeAndId($site->ownerType, $site->ownerId);

        return OwnerQuery::applyToEloquentBuilder($query, $owner, (bool) config('affiliate-network.owner.include_global', false));
    }
}
