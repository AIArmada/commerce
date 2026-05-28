<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Schemas;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use DateTimeInterface;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer Details')
                ->icon(Heroicon::OutlinedUserCircle)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight(FontWeight::SemiBold)
                                ->icon(Heroicon::OutlinedUser),

                            TextEntry::make('email')
                                ->label('Email')
                                ->copyable()
                                ->icon(Heroicon::OutlinedEnvelope),

                            TextEntry::make('phone')
                                ->label('Phone')
                                ->icon(Heroicon::OutlinedPhone)
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Billing Information')
                ->icon(Heroicon::OutlinedCreditCard)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('chip_customer_id')
                                ->label('Chip Customer ID')
                                ->getStateUsing(fn (Model $record): ?string => self::chipCustomerId($record))
                                ->copyable()
                                ->placeholder('Not linked to Chip')
                                ->icon(Heroicon::OutlinedIdentification),

                            TextEntry::make('default_payment_method_type')
                                ->label('Payment Method Type')
                                ->getStateUsing(fn (Model $record): ?string => self::defaultPaymentMethodLabel($record))
                                ->badge()
                                ->color('primary')
                                ->placeholder('No payment method')
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? ucfirst($state) : null),

                            TextEntry::make('default_payment_method_last_four')
                                ->label('Card Last Four')
                                ->getStateUsing(fn (Model $record): ?string => self::defaultPaymentMethodLastFour($record))
                                ->placeholder('—')
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? '•••• ' . $state : null),
                        ]),
                ]),

            Section::make('Subscription Status')
                ->icon(Heroicon::OutlinedBolt)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('subscriptions_count')
                                ->label('Active Subscriptions')
                                ->getStateUsing(function (Model $record): int {
                                    $relationName = self::resolveSubscriptionsRelationName($record);

                                    if ($relationName === null) {
                                        return 0;
                                    }

                                    /** @var Relation $relation */
                                    $relation = $record->{$relationName}();

                                    /** @var Builder<Subscription> $query */
                                    $query = CashierChipOwnerScope::apply($relation->getQuery());

                                    return $query->whereActive()->count();
                                })
                                ->badge()
                                ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                            TextEntry::make('trial_ends_at')
                                ->label('Trial Ends')
                                ->getStateUsing(fn (Model $record): ?Carbon => self::trialEndsAt($record))
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('No trial')
                                ->color(fn (Model $record): ?string => method_exists($record, 'onGenericTrial') && $record->onGenericTrial() ? 'warning' : null),

                            TextEntry::make('on_trial_status')
                                ->label('Trial Status')
                                ->getStateUsing(function (Model $record): string {
                                    if (method_exists($record, 'onGenericTrial') && $record->onGenericTrial()) {
                                        return 'On Trial';
                                    }
                                    if (method_exists($record, 'hasExpiredGenericTrial') && $record->hasExpiredGenericTrial()) {
                                        return 'Trial Expired';
                                    }

                                    return 'No Trial';
                                })
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'On Trial' => 'warning',
                                    'Trial Expired' => 'danger',
                                    default => 'gray',
                                }),
                        ]),
                ]),

            Section::make('Account Information')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Joined')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
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
}
