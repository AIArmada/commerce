<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Schemas;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
                            ->default(0)
                            ->nullable()
                            ->helperText('Days before conversions lock. Leave 0 to inherit the site default.'),

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

                        Select::make('fallback_offer_id')
                            ->label('Fallback offer')
                            ->options(fn (?AffiliateOffer $record): array => self::fallbackOfferOptions($record))
                            ->searchable()
                            ->nullable()
                            ->placeholder('No fallback offer')
                            ->helperText('Sent to the redirect gate when the primary offer is unavailable.'),

                        DateTimePicker::make('starts_at')
                            ->nullable(),

                        DateTimePicker::make('ends_at')
                            ->nullable()
                            ->afterOrEqual('starts_at'),
                    ])
                    ->columns(4),

                Section::make('Advanced')
                    ->schema([
                        Repeater::make('event_rates')
                            ->label('Event rates')
                            ->schema([
                                TextInput::make('event_type')
                                    ->label('Event type')
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('rate_base_bp')
                                    ->label('Rate (bp)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->nullable(),
                                TextInput::make('rate_fixed_minor')
                                    ->label('Fixed (minor)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->nullable(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull()
                            ->formatStateUsing(fn (mixed $state): array => self::hydrateEventRates($state))
                            ->dehydrateStateUsing(fn (mixed $state): ?array => self::dehydrateEventRates($state))
                            ->mutateDehydratedStateUsing(fn (mixed $state): ?array => self::dehydrateEventRates($state))
                            ->helperText('Per-event CPA overrides. Leave empty to use the offer rate.'),

                        Repeater::make('active_promotions')
                            ->label('Active promotions')
                            ->schema([
                                TextInput::make('name')
                                    ->maxLength(120),
                                TextInput::make('bonus_rate_bp')
                                    ->label('Bonus rate (bp)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->nullable(),
                                TextInput::make('override_rate_bp')
                                    ->label('Override rate (bp)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->nullable(),
                                TextInput::make('bonus_fixed_minor')
                                    ->label('Bonus fixed (minor)')
                                    ->rule('integer')
                                    ->minValue(0)
                                    ->nullable(),
                                TextInput::make('multiplier')
                                    ->rule('numeric')
                                    ->minValue(0)
                                    ->nullable(),
                                DateTimePicker::make('starts_at')
                                    ->nullable(),
                                DateTimePicker::make('ends_at')
                                    ->nullable(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull()
                            ->dehydrateStateUsing(fn (mixed $state): ?array => self::dehydratePromotions($state)),

                        TagsInput::make('restrictions.allowed_countries')
                            ->label('Allowed countries')
                            ->nullable(),

                        TagsInput::make('restrictions.blocked_countries')
                            ->label('Blocked countries')
                            ->nullable(),

                        TagsInput::make('restrictions.allowed_channels')
                            ->label('Allowed channels')
                            ->nullable(),

                        TagsInput::make('restrictions.blocked_channels')
                            ->label('Blocked channels')
                            ->nullable(),

                        Select::make('restrictions.allowed_devices')
                            ->label('Allowed devices')
                            ->multiple()
                            ->options(self::deviceOptions())
                            ->nullable(),

                        Select::make('restrictions.blocked_devices')
                            ->label('Blocked devices')
                            ->multiple()
                            ->options(self::deviceOptions())
                            ->nullable(),

                        TextInput::make('restrictions.min_order_minor')
                            ->label('Min order (minor)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        TextInput::make('restrictions.max_order_minor')
                            ->label('Max order (minor)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        KeyValue::make('metadata')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
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

    /**
     * @return array<string, string>
     */
    private static function deviceOptions(): array
    {
        return [
            'desktop' => 'Desktop',
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function fallbackOfferOptions(?AffiliateOffer $record): array
    {
        $options = [];

        $offers = AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->where('status', OfferStatus::Published)
            ->when($record !== null, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($offers as $offer) {
            $options[(string) $offer->getKey()] = (string) $offer->name;
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeData(array $data): array
    {
        if (array_key_exists('locking_days', $data) && ($data['locking_days'] === null || $data['locking_days'] === '')) {
            $data['locking_days'] = 0;
        }

        if (array_key_exists('event_rates', $data)) {
            $data['event_rates'] = self::dehydrateEventRates($data['event_rates']);
        }

        $restrictions = $data['restrictions'] ?? null;
        $data['restrictions'] = self::normalizeRestrictions(is_array($restrictions) ? $restrictions : null);

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function hydrateEventRates(mixed $state): array
    {
        if (! is_array($state) || $state === []) {
            return [];
        }

        if (array_is_list($state)) {
            return array_values(array_filter($state, static fn (mixed $row): bool => is_array($row)));
        }

        $rows = [];

        foreach ($state as $eventType => $rate) {
            if (! is_array($rate)) {
                continue;
            }

            $rows[] = [
                'event_type' => (string) $eventType,
                'rate_base_bp' => $rate['rate_base_bp'] ?? null,
                'rate_fixed_minor' => $rate['rate_fixed_minor'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, int>>|null
     */
    private static function dehydrateEventRates(mixed $state): ?array
    {
        if (! is_array($state)) {
            return null;
        }

        $rates = [];

        foreach ($state as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $eventType = array_key_exists('event_type', $row)
                ? mb_trim((string) $row['event_type'])
                : (is_string($key) && $key !== '' && ! is_numeric($key) ? mb_trim($key) : '');

            if ($eventType === '') {
                continue;
            }

            $values = array_filter([
                'rate_base_bp' => self::intOrNull($row['rate_base_bp'] ?? null),
                'rate_fixed_minor' => self::intOrNull($row['rate_fixed_minor'] ?? null),
            ], static fn (?int $value): bool => $value !== null);

            if ($values === []) {
                continue;
            }

            $rates[$eventType] = $values;
        }

        return $rates === [] ? null : $rates;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private static function dehydratePromotions(mixed $state): ?array
    {
        if (! is_array($state)) {
            return null;
        }

        $promotions = [];

        foreach ($state as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = isset($row['name']) ? mb_trim((string) $row['name']) : '';
            $entry = array_filter([
                'name' => $name !== '' ? $name : null,
                'bonus_rate_bp' => self::intOrNull($row['bonus_rate_bp'] ?? null),
                'override_rate_bp' => self::intOrNull($row['override_rate_bp'] ?? null),
                'bonus_fixed_minor' => self::intOrNull($row['bonus_fixed_minor'] ?? null),
                'multiplier' => self::floatOrNull($row['multiplier'] ?? null),
                'starts_at' => self::stringOrNull($row['starts_at'] ?? null),
                'ends_at' => self::stringOrNull($row['ends_at'] ?? null),
            ], static fn (mixed $value): bool => $value !== null);

            if ($entry === []) {
                continue;
            }

            $promotions[] = $entry;
        }

        return $promotions === [] ? null : $promotions;
    }

    /**
     * @param  array<string, mixed>|null  $restrictions
     * @return array<string, mixed>|null
     */
    private static function normalizeRestrictions(?array $restrictions): ?array
    {
        if ($restrictions === null) {
            return null;
        }

        $normalized = [];

        foreach ($restrictions as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($key === 'min_order_minor' || $key === 'max_order_minor') {
                $integer = self::intOrNull($value);

                if ($integer !== null) {
                    $normalized[$key] = $integer;
                }

                continue;
            }

            if (is_array($value)) {
                $list = array_values(array_filter(
                    $value,
                    static fn (mixed $item): bool => is_string($item) && mb_trim($item) !== '',
                ));

                if ($list !== []) {
                    $normalized[$key] = $list;
                }

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized === [] ? null : $normalized;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && mb_trim($value) !== '' ? $value : null;
    }
}
