<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Schemas;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Platform\DomainHostValidator;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AffiliateSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('domain')
                            ->required()
                            ->maxLength(255)
                            ->mutateStateForValidationUsing(fn (mixed $state): mixed => is_string($state) ? mb_strtolower(mb_trim($state)) : $state)
                            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                if (! is_string($value) || ! DomainHostValidator::isValidHostname($value)) {
                                    $fail('Enter a bare domain name without a scheme, path, or whitespace (e.g. example.com).');
                                }
                            })
                            ->unique(ignoreRecord: true)
                            ->helperText('Enter the domain without http:// or https://'),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options(self::statusOptions())
                            ->required()
                            ->default(SiteStatus::Pending->value),

                        Select::make('verification_method')
                            ->options([
                                'dns' => 'DNS TXT Record',
                                'meta_tag' => 'HTML Meta Tag',
                                'file' => 'Verification File',
                            ])
                            ->nullable(),

                        DateTimePicker::make('verified_at')
                            ->nullable()
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Catalog Sync')
                    ->description('Point at a merchant affiliates install to import its public programs as offers. Leave empty for locally-managed offers.')
                    ->schema([
                        TextInput::make('catalog_url')
                            ->label('Catalog URL')
                            ->url()
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('Base API URL of the merchant site, e.g. https://merchant.example.com/api/affiliates'),

                        TextInput::make('catalog_token')
                            ->label('Catalog API token')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->helperText('Stored encrypted. Leave empty to keep the current token.'),
                    ])
                    ->columns(2),

                Section::make('Settlement & Locking')
                    ->description('Payout settlement policy for this merchant site.')
                    ->schema([
                        TextInput::make('default_locking_days')
                            ->label('Default locking days')
                            ->rule('integer')
                            ->minValue(0)
                            ->maxValue(365)
                            ->nullable(),

                        TextInput::make('prepaid_balance_minor')
                            ->label('Prepaid balance (minor)')
                            ->rule('integer')
                            ->nullable(),

                        TextInput::make('budget_currency')
                            ->label('Budget currency')
                            ->maxLength(3)
                            ->nullable()
                            ->placeholder('MYR'),

                        TextInput::make('monthly_spend_cap_minor')
                            ->label('Monthly spend cap (minor)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),

                        Toggle::make('require_settled_for_payout')
                            ->label('Require settled statement for payout')
                            ->default(false),
                    ])
                    ->columns(3),

                Section::make('Settings')
                    ->schema([
                        KeyValue::make('settings')
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
    private static function statusOptions(): array
    {
        $options = [];

        foreach (SiteStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
