# Tax Zone Resource

> **Document:** 02 of 03  
> **Package:** `aiarmada/filament-tax`  
> **Status:** Vision

---

## Overview

The TaxZoneResource provides comprehensive tax configuration including zones, rates, and exemptions.

---

## Tax Zone Form

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Zone Information')->schema([
            TextInput::make('name')->required(),
            TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash(),
            Select::make('type')
                ->options([
                    'country' => 'Country',
                    'state' => 'State/Province',
                    'postal' => 'Postal Code Range',
                    'region' => 'Custom Region',
                ])
                ->required()
                ->live(),
        ]),

        Section::make('Geographic Scope')
            ->schema([
                Select::make('countries')
                    ->options(Countries::all())
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->visible(fn ($get) => in_array($get('type'), ['country', 'state', 'region'])),
                Select::make('states')
                    ->options(fn ($get) => States::forCountries($get('countries') ?? []))
                    ->multiple()
                    ->searchable()
                    ->visible(fn ($get) => $get('type') === 'state'),
                TagsInput::make('postal_codes')
                    ->placeholder('Add postal codes or ranges (e.g., 47000-47999)')
                    ->visible(fn ($get) => $get('type') === 'postal'),
            ]),

        Section::make('Settings')->schema([
            Toggle::make('is_default')
                ->helperText('Fallback zone when no other matches'),
            TextInput::make('priority')
                ->numeric()
                ->default(0)
                ->helperText('Higher priority zones are checked first'),
        ])->columns(2),
    ]);
}
```

---

## Tax Rate Relation Manager

```php
class TaxRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tax_class_id')
                ->relationship('taxClass', 'name')
                ->required()
                ->preload(),
            TextInput::make('name')
                ->required()
                ->placeholder('e.g., SST, VAT, GST'),
            TextInput::make('rate')
                ->numeric()
                ->suffix('%')
                ->required(),
            Toggle::make('is_compound')
                ->helperText('Apply after other taxes'),
            Toggle::make('is_shipping')
                ->helperText('Apply to shipping costs')
                ->default(true),
            TextInput::make('priority')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('taxClass.name'),
                TextColumn::make('name'),
                TextColumn::make('rate')
                    ->suffix('%'),
                IconColumn::make('is_compound')->boolean(),
                IconColumn::make('is_shipping')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

---

## Tax Class Resource

```php
class TaxClassResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true),
            Textarea::make('description'),
            Toggle::make('is_default')
                ->helperText('Default class for new products'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('code'),
                IconColumn::make('is_default')->boolean(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
            ]);
    }
}
```

---

## Tax Exemption Resource

```php
class TaxExemptionResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Customer')->schema([
                Select::make('customer_id')
                    ->relationship('customer', 'email')
                    ->searchable()
                    ->required(),
                Select::make('tax_zone_id')
                    ->relationship('taxZone', 'name')
                    ->helperText('Leave empty for all zones'),
            ])->columns(2),

            Section::make('Certificate')->schema([
                TextInput::make('certificate_number'),
                FileUpload::make('certificate_file')
                    ->acceptedFileTypes(['application/pdf', 'image/*']),
                Textarea::make('reason'),
            ]),

            Section::make('Validity')->schema([
                DatePicker::make('starts_at'),
                DatePicker::make('expires_at'),
                Toggle::make('is_verified')->disabled(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.full_name'),
                TextColumn::make('certificate_number'),
                TextColumn::make('taxZone.name')->placeholder('All Zones'),
                TextColumn::make('expires_at')
                    ->date()
                    ->color(fn ($record) => 
                        $record->expires_at?->isPast() ? 'danger' : 
                        ($record->expires_at?->isBefore(now()->addDays(30)) ? 'warning' : 'success')
                    ),
                IconColumn::make('is_verified')->boolean(),
            ])
            ->filters([
                Filter::make('expiring_soon')
                    ->query(fn ($query) => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now()->addDays(30))
                        ->where('expires_at', '>=', now())
                    ),
                Filter::make('expired')
                    ->query(fn ($query) => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now())
                    ),
            ]);
    }
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-dashboard-widgets.md](03-dashboard-widgets.md)
