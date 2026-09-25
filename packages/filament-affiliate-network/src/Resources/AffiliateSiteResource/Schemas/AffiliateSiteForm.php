<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Schemas;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            // Normalize before validation so the format and unique rules
                            // see the canonical lowercase form (also persisted as such by
                            // the Create/Edit pages).
                            ->mutateStateForValidationUsing(fn (mixed $state): mixed => is_string($state) ? mb_strtolower(mb_trim($state)) : $state)
                            ->regex('/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/')
                            ->validationMessages([
                                'regex' => 'Enter a bare domain name without a scheme, path, or whitespace (e.g. example.com).',
                            ])
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
                            ->options([
                                AffiliateSite::STATUS_PENDING => 'Pending',
                                AffiliateSite::STATUS_VERIFIED => 'Verified',
                                AffiliateSite::STATUS_SUSPENDED => 'Suspended',
                                AffiliateSite::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required()
                            ->default(AffiliateSite::STATUS_PENDING),

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
     * Fold a submitted catalog token into the encrypted column.
     *
     * A blank token leaves the stored value untouched so edits never wipe
     * credentials by accident.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeCatalogToken(array $data): array
    {
        $token = $data['catalog_token'] ?? null;
        unset($data['catalog_token']);

        if (is_string($token) && $token !== '') {
            $data['catalog_token_encrypted'] = encrypt($token);
            $data['catalog_token_issued_at'] = now();
        }

        return $data;
    }
}
