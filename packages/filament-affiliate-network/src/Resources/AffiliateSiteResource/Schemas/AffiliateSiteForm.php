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
}
