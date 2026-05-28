<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables;

use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use DateTimeInterface;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class CustomerTable
{
    /** @var array<string, bool> */
    private static array $genericTrialQuerySupport = [];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                TextColumn::make('chip_customer_id')
                    ->label('Chip ID')
                    ->getStateUsing(fn (Model $record): ?string => self::chipCustomerId($record))
                    ->copyable()
                    ->placeholder('Not linked')
                    ->toggleable(),

                IconColumn::make('has_chip_customer_link')
                    ->label('Linked')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Model $record): bool => method_exists($record, 'hasChipId') && $record->hasChipId()),

                TextColumn::make('default_payment_method')
                    ->label('Payment Method')
                    ->getStateUsing(fn (Model $record): ?string => self::defaultPaymentMethodLabel($record))
                    ->badge()
                    ->color('primary')
                    ->placeholder('None')
                    ->formatStateUsing(
                        fn (?string $state, Model $record): ?string => $state !== null
                        ? ucfirst($state) . ' •••• ' . (self::defaultPaymentMethodLastFour($record) ?? '****')
                        : null
                    ),

                TextColumn::make('subscriptions_count')
                    ->label('Subscriptions')
                    ->getStateUsing(function (Model $record): int {
                        $relationName = self::resolveSubscriptionsRelationName($record);

                        if ($relationName === null) {
                            return 0;
                        }

                        /** @var Relation $relation */
                        $relation = $record->{$relationName}();

                        return CashierChipOwnerScope::apply($relation->getQuery())->count();
                    })
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                IconColumn::make('on_trial')
                    ->label('Trial')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Model $record): bool => method_exists($record, 'onTrial') && $record->onTrial()),

                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->getStateUsing(fn (Model $record): ?Carbon => self::trialEndsAt($record))
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        if (! self::supportsGenericTrialQuery($query->getModel())) {
                            return $query;
                        }

                        return $query->orderBy('trial_ends_at', $direction);
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('has_chip_customer_link')
                    ->label('Chip Linked')
                    ->placeholder('All')
                    ->trueLabel('Linked to Chip')
                    ->falseLabel('Not Linked')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('chipCustomerLink'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('chipCustomerLink'),
                    ),

                TernaryFilter::make('has_payment_method')
                    ->label('Payment Method')
                    ->placeholder('All')
                    ->trueLabel('Has Payment Method')
                    ->falseLabel('No Payment Method')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('storedPaymentMethods'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('storedPaymentMethods'),
                    ),

                Filter::make('has_subscriptions')
                    ->label('Has Subscriptions')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        $relationName = self::resolveSubscriptionsRelationName($query->getModel());

                        if ($relationName === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereHas(
                            $relationName,
                            fn (Builder $subscriptionsQuery): Builder => CashierChipOwnerScope::apply($subscriptionsQuery),
                        );
                    }),

                Filter::make('on_trial')
                    ->label('On Trial')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        if (! self::supportsGenericTrialQuery($query->getModel())) {
                            return $query;
                        }

                        return $query->whereNotNull('trial_ends_at')
                            ->where('trial_ends_at', '>', now());
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-cashier-chip.tables.polling_interval', '45s'));
    }

    private static function resolveSubscriptionsRelationName(Model $model): ?string
    {
        if (method_exists($model, 'subscriptions')) {
            return 'subscriptions';
        }

        if (method_exists($model, 'chipSubscriptions')) {
            return 'chipSubscriptions';
        }

        return null;
    }

    private static function chipCustomerId(Model $record): ?string
    {
        if (! is_callable([$record, 'chipId'])) {
            return null;
        }

        $chipCustomerId = call_user_func([$record, 'chipId']);

        return is_string($chipCustomerId) && $chipCustomerId !== '' ? $chipCustomerId : null;
    }

    private static function defaultPaymentMethod(Model $record): mixed
    {
        if (! is_callable([$record, 'defaultPaymentMethod'])) {
            return null;
        }

        return call_user_func([$record, 'defaultPaymentMethod']);
    }

    private static function defaultPaymentMethodLabel(Model $record): ?string
    {
        $paymentMethod = self::defaultPaymentMethod($record);

        if (! is_object($paymentMethod)) {
            return null;
        }

        if (is_callable([$paymentMethod, 'brand'])) {
            $brand = call_user_func([$paymentMethod, 'brand']);

            if (is_string($brand) && $brand !== '') {
                return $brand;
            }
        }

        if (! is_callable([$paymentMethod, 'type'])) {
            return null;
        }

        $type = call_user_func([$paymentMethod, 'type']);

        return is_string($type) && $type !== '' ? $type : null;
    }

    private static function defaultPaymentMethodLastFour(Model $record): ?string
    {
        $paymentMethod = self::defaultPaymentMethod($record);

        if (! is_object($paymentMethod) || ! is_callable([$paymentMethod, 'lastFour'])) {
            return null;
        }

        $lastFour = call_user_func([$paymentMethod, 'lastFour']);

        return is_string($lastFour) && $lastFour !== '' ? $lastFour : null;
    }

    private static function trialEndsAt(Model $record): ?Carbon
    {
        if (! self::supportsGenericTrialValue($record)) {
            return null;
        }

        $trialEndsAt = $record->getAttribute('trial_ends_at');

        if ($trialEndsAt instanceof Carbon) {
            return $trialEndsAt;
        }

        if ($trialEndsAt instanceof DateTimeInterface) {
            return Carbon::instance($trialEndsAt);
        }

        if (is_string($trialEndsAt) && $trialEndsAt !== '') {
            return Carbon::parse($trialEndsAt);
        }

        return null;
    }

    private static function supportsGenericTrialValue(Model $record): bool
    {
        return array_key_exists('trial_ends_at', $record->getAttributes())
            || array_key_exists('trial_ends_at', $record->getCasts());
    }

    private static function supportsGenericTrialQuery(Model $model): bool
    {
        $connectionName = $model->getConnectionName();
        $cacheKey = $connectionName . '|' . $model->getTable();

        if (! array_key_exists($cacheKey, self::$genericTrialQuerySupport)) {
            try {
                self::$genericTrialQuerySupport[$cacheKey] = $connectionName !== null
                    ? Schema::connection($connectionName)->hasColumn($model->getTable(), 'trial_ends_at')
                    : Schema::hasColumn($model->getTable(), 'trial_ends_at');
            } catch (Throwable) {
                self::$genericTrialQuerySupport[$cacheKey] = false;
            }
        }

        return self::$genericTrialQuerySupport[$cacheKey];
    }
}
