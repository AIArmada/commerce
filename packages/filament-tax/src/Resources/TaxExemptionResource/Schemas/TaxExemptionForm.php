<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get as GetFormState;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TaxExemptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Customer Information')
                            ->schema([
                                Select::make('exemptable_type')
                                    ->label('Entity Type')
                                    ->options([
                                        'AIArmada\\Customers\\Models\\Customer' => 'Customer',
                                        'AIArmada\\Customers\\Models\\CustomerGroup' => 'Customer Group',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('AIArmada\\Customers\\Models\\Customer'),

                                Select::make('exemptable_id')
                                    ->label('Customer')
                                    ->searchable()
                                    ->required()
                                    ->options(function (GetFormState $get) {
                                        $type = $get('exemptable_type');

                                        if ($type === 'AIArmada\\Customers\\Models\\Customer') {
                                            return \AIArmada\Customers\Models\Customer::query()
                                                ->get()
                                                ->mapWithKeys(fn ($c) => [$c->id => $c->full_name . ' (' . $c->email . ')']);
                                        }

                                        if ($type === 'AIArmada\\Customers\\Models\\CustomerGroup') {
                                            return \AIArmada\Customers\Models\CustomerGroup::pluck('name', 'id');
                                        }

                                        return [];
                                    }),

                                Select::make('tax_zone_id')
                                    ->label('Tax Zone')
                                    ->relationship('taxZone', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Leave empty to apply exemption to all zones'),
                            ])
                            ->columns(3),

                        Section::make('Certificate Details')
                            ->schema([
                                TextInput::make('certificate_number')
                                    ->label('Certificate Number')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                FileUpload::make('certificate_file')
                                    ->label('Certificate Document')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->disk('public')
                                    ->directory('tax-exemptions')
                                    ->downloadable()
                                    ->openable(),

                                Textarea::make('reason')
                                    ->label('Exemption Reason')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Validity Period')
                            ->schema([
                                DatePicker::make('starts_at')
                                    ->label('Effective From')
                                    ->default(now())
                                    ->native(false),

                                DatePicker::make('expires_at')
                                    ->label('Expires At')
                                    ->native(false)
                                    ->after('starts_at')
                                    ->helperText('Leave blank for permanent exemption'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_verified')
                                    ->label('Verified')
                                    ->helperText('Mark as verified after review')
                                    ->default(false),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Placeholder::make('status_info')
                                    ->label('Status')
                                    ->content(function ($record) {
                                        if (! $record) {
                                            return 'New exemption';
                                        }

                                        if ($record->expires_at && $record->expires_at->isPast()) {
                                            return '⚠️ Expired';
                                        }

                                        if ($record->expires_at && $record->expires_at->isBefore(now()->addDays(30))) {
                                            return '⏰ Expiring Soon';
                                        }

                                        if ($record->is_verified) {
                                            return '✅ Active & Verified';
                                        }

                                        return '⏳ Pending Verification';
                                    }),
                            ]),

                        Section::make('Notes')
                            ->schema([
                                Textarea::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->rows(4)
                                    ->helperText('For internal use only'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
