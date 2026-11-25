<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Schemas;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CreatorPostbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Endpoint')
                    ->schema([
                        TextInput::make('affiliate_id')
                            ->label('Affiliate ID')
                            ->required()
                            ->maxLength(255),

                        Select::make('offer_id')
                            ->label('Offer')
                            ->options(fn (): array => self::offerOptions())
                            ->searchable()
                            ->nullable()
                            ->placeholder('All offers')
                            ->helperText('Leave empty to receive events for every offer.'),

                        TextInput::make('url_template')
                            ->label('URL template')
                            ->required()
                            ->maxLength(2048)
                            ->columnSpanFull()
                            ->helperText('Tokens: {leg_id}, {offer_id}, {external_reference}, {event_type}, {status}, {revenue_minor}, {commission_minor}, {payout_minor}, {currency}, {sub1}, {sub2}, {sub3}, {coupon_code}'),

                        Select::make('http_method')
                            ->label('HTTP method')
                            ->options([
                                'GET' => 'GET',
                                'POST' => 'POST',
                            ])
                            ->required()
                            ->default('GET'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Delivery')
                    ->schema([
                        CheckboxList::make('events')
                            ->label('Events')
                            ->options(self::eventOptions())
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->columnSpanFull(),

                        TextInput::make('signing_secret')
                            ->label('Signing secret')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Used to sign deliveries. Leave blank on edit to keep the existing secret.'),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function eventOptions(): array
    {
        $options = [];

        foreach (PostbackEventType::cases() as $event) {
            $options[$event->value] = $event->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function offerOptions(): array
    {
        $options = [];

        $offers = AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($offers as $offer) {
            $options[(string) $offer->getKey()] = (string) $offer->name;
        }

        return $options;
    }
}
