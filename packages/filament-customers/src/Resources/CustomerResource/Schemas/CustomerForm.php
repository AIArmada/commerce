<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Customer Information')
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        $owner = OwnerUiScope::resolveOwner(Customer::class);
                                        if ($owner !== null) {
                                            return $rule
                                                ->where('owner_type', $owner->getMorphClass())
                                                ->where('owner_id', $owner->getKey());
                                        }

                                        return $rule
                                            ->whereNull('owner_type')
                                            ->whereNull('owner_id');
                                    })
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(20),

                                TextInput::make('company')
                                    ->label('Company')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Section::make('Preferences')
                            ->schema([
                                Toggle::make('accepts_marketing')
                                    ->label('Accepts Marketing')
                                    ->helperText('Customer has opted in for marketing emails'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(
                                        collect(CustomerStatus::cases())
                                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    )
                                    ->required()
                                    ->default('active'),
                            ]),

                        Section::make('Segments')
                            ->schema([
                                Select::make('segments')
                                    ->label('Segments')
                                    ->relationship(
                                        name: 'segments',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false)
                                            ->where('is_automatic', false),
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('Manual segment assignment')
                                    ->saveRelationshipsUsing(function (Customer $record, ?array $state): void {
                                        static::syncManualSegments($record, $state);
                                    }),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    /**
     * @param  array<int, mixed>|null  $state
     *
     * @throws HttpException
     */
    public static function syncManualSegments(Customer $record, ?array $state): void
    {
        $owner = static::ensureRecordOwnerScope($record);

        $ids = array_values(array_filter($state ?? []));

        $allowedManualSegmentIds = Segment::query()
            ->forOwner($owner, includeGlobal: false)
            ->where('is_automatic', false)
            ->whereKey($ids)
            ->pluck('id')
            ->all();

        abort_unless(array_diff($ids, $allowedManualSegmentIds) === [], 403);

        $automaticSegmentIds = $record->segments()
            ->where('is_automatic', true)
            ->pluck('id')
            ->all();

        $record->segments()->sync(array_values(array_unique([
            ...$automaticSegmentIds,
            ...$allowedManualSegmentIds,
        ])));
    }

    protected static function ensureRecordOwnerScope(Customer $record): ?Model
    {
        $owner = OwnerUiScope::resolveOwner(Customer::class);

        if ($owner === null) {
            abort_unless($record->owner_type === null && $record->owner_id === null, 403);

            return null;
        }

        abort_unless($record->belongsToOwner($owner), 403);

        return $owner;
    }
}
