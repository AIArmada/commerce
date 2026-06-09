<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\SegmentResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Segment Information')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Segment Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, ?string $state) => $set('slug', Str::slug($state))
                                    ),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        $owner = OwnerUiScope::resolveOwner(Segment::class);

                                        if ($owner !== null) {
                                            return $rule
                                                ->where('owner_type', $owner->getMorphClass())
                                                ->where('owner_id', $owner->getKey());
                                        }

                                        return $rule
                                            ->whereNull('owner_type')
                                            ->whereNull('owner_id');
                                    }),

                                Select::make('type')
                                    ->label('Type')
                                    ->options(
                                        collect(SegmentType::cases())
                                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                    )
                                    ->required()
                                    ->default('custom'),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Assignment Rules')
                            ->schema([
                                Toggle::make('is_automatic')
                                    ->label('Automatic Assignment')
                                    ->helperText('Automatically assign customers based on conditions')
                                    ->live(),

                                Repeater::make('conditions')
                                    ->label('Conditions')
                                    ->schema([
                                        Select::make('field')
                                            ->label('Field')
                                            ->options([
                                                'accepts_marketing' => 'Accepts Marketing',
                                                'status' => 'Customer Status',
                                                'created_days_ago' => 'Customer for X Days',
                                            ])
                                            ->required()
                                            ->live(),

                                        TextInput::make('value_numeric')
                                            ->label('Value')
                                            ->required()
                                            ->numeric()
                                            ->visible(fn (Get $get) => in_array($get('field'), ['created_days_ago']))
                                            ->dehydratedWhenHidden(),

                                        Toggle::make('value_boolean')
                                            ->label('Value')
                                            ->visible(fn (Get $get) => in_array($get('field'), ['accepts_marketing']))
                                            ->dehydratedWhenHidden(),

                                        Select::make('value_status')
                                            ->label('Status')
                                            ->options(
                                                collect(CustomerStatus::cases())
                                                    ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                            )
                                            ->visible(fn (Get $get) => $get('field') === 'status')
                                            ->dehydratedWhenHidden(),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Condition')
                                    ->visible(fn (Get $get) => $get('is_automatic') === true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = more important for pricing'),
                            ]),

                        Section::make('Manual Assignment')
                            ->schema([
                                Select::make('customers')
                                    ->label('Customers')
                                    ->relationship(
                                        name: 'customers',
                                        titleAttribute: 'email',
                                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('For manual segments only')
                                    ->saveRelationshipsUsing(function (Segment $record, ?array $state): void {
                                        static::syncManualCustomers($record, $state);
                                    }),
                            ])
                            ->visible(fn (Get $get) => ! $get('is_automatic')),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    /**
     * @param  array<int, mixed>|null  $state
     */
    public static function syncManualCustomers(Segment $record, ?array $state): void
    {
        $owner = static::ensureRecordOwnerScope($record);

        $ids = array_values(array_filter($state ?? []));

        if ($ids === []) {
            $record->customers()->sync([]);

            return;
        }

        $allowedIds = Customer::query()
            ->forOwner($owner, includeGlobal: false)
            ->whereKey($ids)
            ->pluck('id')
            ->all();

        abort_unless(array_diff($ids, $allowedIds) === [], 403);

        $record->customers()->sync($allowedIds);
    }

    protected static function ensureRecordOwnerScope(Segment $record): ?Model
    {
        $owner = OwnerUiScope::resolveOwner(Segment::class);

        if ($owner === null) {
            abort_unless($record->owner_type === null && $record->owner_id === null, 403);

            return null;
        }

        abort_unless($record->belongsToOwner($owner), 403);

        return $owner;
    }
}
