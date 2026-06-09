<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Schemas;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class SubscriptionForm
{
    /**
     * @return array<int, Wizard>
     */
    public static function schema(): array
    {
        return [
            self::createWizard(),
        ];
    }

    private static function createWizard(): Wizard
    {
        $gatewayDetector = app(GatewayDetector::class);

        return Wizard::make([
            Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.customer'))
                ->icon('heroicon-o-user')
                ->schema([
                    Select::make('billable_id')
                        ->label(__('filament-cashier::subscriptions.create.customer_label'))
                        ->options(fn () => self::getCustomerOptions())
                        ->searchable()
                        ->required()
                        ->live(),
                ]),

            Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.gateway'))
                ->icon('heroicon-o-credit-card')
                ->schema([
                    Radio::make('gateway')
                        ->label(__('filament-cashier::subscriptions.create.gateway_label'))
                        ->options($gatewayDetector->getGatewayOptions())
                        ->descriptions([
                            'stripe' => __('filament-cashier::subscriptions.create.gateway_stripe_description'),
                            'chip' => __('filament-cashier::subscriptions.create.gateway_chip_description'),
                        ])
                        ->required()
                        ->live()
                        ->columns(2),
                ]),

            Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.plan'))
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    Section::make()
                        ->schema([
                            TextInput::make('type')
                                ->label('Subscription Type')
                                ->default('default')
                                ->required(),

                            Select::make('plan_id')
                                ->label(__('filament-cashier::subscriptions.create.plan_label'))
                                ->options(fn (Get $get) => self::getPlansForGateway($get('gateway')))
                                ->searchable()
                                ->required(),

                            TextInput::make('quantity')
                                ->label(__('filament-cashier::subscriptions.create.quantity_label'))
                                ->numeric()
                                ->default(1)
                                ->minValue(1),

                            Toggle::make('has_trial')
                                ->label(__('filament-cashier::subscriptions.create.has_trial_label'))
                                ->live(),

                            TextInput::make('trial_days')
                                ->label(__('filament-cashier::subscriptions.create.trial_days_label'))
                                ->numeric()
                                ->default(14)
                                ->minValue(1)
                                ->visible(fn (Get $get): bool => (bool) $get('has_trial')),
                        ]),
                ]),

            Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.payment'))
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Select::make('payment_method')
                        ->label(__('filament-cashier::subscriptions.create.payment_method_label'))
                        ->placeholder(__('filament-cashier::subscriptions.create.payment_method_placeholder'))
                        ->options(fn (Get $get) => self::getPaymentMethodsForBillable($get('billable_id'), $get('gateway'))),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getCustomerOptions(): array
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel) || ! is_a($billableModel, Model::class, true)) {
            return [];
        }

        return OwnerScopedQuery::apply($billableModel::query())
            ->limit(100)
            ->get()
            ->mapWithKeys(fn ($user) => [
                $user->getKey() => $user->name ?? $user->email ?? (string) $user->getKey(),
            ])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    public static function getPlansForGateway(?string $gateway): array
    {
        if ($gateway === null) {
            return [];
        }

        $configPlans = config("cashier.gateways.{$gateway}.plans", []);

        if (! empty($configPlans)) {
            return $configPlans;
        }

        return match ($gateway) {
            'stripe' => [
                'price_basic_monthly' => 'Basic Monthly - ' . MoneyFormatter::formatMajor(9, 'USD') . '/mo',
                'price_pro_monthly' => 'Pro Monthly - ' . MoneyFormatter::formatMajor(29, 'USD') . '/mo',
                'price_premium_monthly' => 'Premium Monthly - ' . MoneyFormatter::formatMajor(99, 'USD') . '/mo',
                'price_basic_yearly' => 'Basic Yearly - ' . MoneyFormatter::formatMajor(90, 'USD') . '/yr',
                'price_pro_yearly' => 'Pro Yearly - ' . MoneyFormatter::formatMajor(290, 'USD') . '/yr',
            ],
            'chip' => [
                'plan_basic_monthly' => 'Basic Monthly - ' . MoneyFormatter::formatMajor(39, 'MYR') . '/mo',
                'plan_pro_monthly' => 'Pro Monthly - ' . MoneyFormatter::formatMajor(99, 'MYR') . '/mo',
                'plan_premium_monthly' => 'Premium Monthly - ' . MoneyFormatter::formatMajor(299, 'MYR') . '/mo',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function getPaymentMethodsForBillable(?string $billableId, ?string $gateway): array
    {
        if ($billableId === null || $gateway === null) {
            return [];
        }

        $gatewayDetector = app(GatewayDetector::class);

        if (! $gatewayDetector->isAvailable($gateway)) {
            return [];
        }

        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel) || ! is_a($billableModel, Model::class, true)) {
            return [];
        }

        $billable = OwnerScopedQuery::apply($billableModel::query())
            ->whereKey($billableId)
            ->first();

        if ($billable === null) {
            return [];
        }

        try {
            if ($gateway === 'stripe' && method_exists($billable, 'paymentMethods')) {
                $paymentMethods = $billable->paymentMethods();

                if (! is_iterable($paymentMethods)) {
                    return [];
                }

                return collect($paymentMethods)
                    ->mapWithKeys(fn ($pm) => [
                        data_get($pm, 'id') => (data_get($pm, 'card.brand') ?? 'Card') . ' **** ' . (data_get($pm, 'card.last4') ?? '****'),
                    ])
                    ->toArray();
            }

            if ($gateway === 'chip' && method_exists($billable, 'paymentMethods')) {
                $paymentMethods = $billable->paymentMethods();

                if (! is_iterable($paymentMethods)) {
                    return [];
                }

                return collect($paymentMethods)
                    ->mapWithKeys(function (mixed $paymentMethod): array {
                        $paymentMethodId = self::getChipPaymentMethodId($paymentMethod);

                        if ($paymentMethodId === null) {
                            return [];
                        }

                        return [
                            $paymentMethodId => self::getChipPaymentMethodLabel($paymentMethod),
                        ];
                    })
                    ->toArray();
            }
        } catch (Throwable) {
            // Silently fail if gateway API is not configured
        }

        return [];
    }

    public static function getChipPaymentMethodId(mixed $paymentMethod): ?string
    {
        if (is_object($paymentMethod) && method_exists($paymentMethod, 'id')) {
            $paymentMethodId = $paymentMethod->id();

            if (is_string($paymentMethodId) && $paymentMethodId !== '') {
                return $paymentMethodId;
            }
        }

        $paymentMethodId = data_get($paymentMethod, 'id') ?? data_get($paymentMethod, 'recurring_token');

        return is_string($paymentMethodId) && $paymentMethodId !== '' ? $paymentMethodId : null;
    }

    public static function getChipPaymentMethodLabel(mixed $paymentMethod): string
    {
        $brand = 'Card';

        if (is_object($paymentMethod) && method_exists($paymentMethod, 'brand')) {
            $brand = $paymentMethod->brand() ?? $paymentMethod->type() ?? $brand;
        } else {
            $brand = data_get($paymentMethod, 'brand')
                ?? data_get($paymentMethod, 'card_brand')
                ?? data_get($paymentMethod, 'type')
                ?? data_get($paymentMethod, 'payment_method')
                ?? $brand;
        }

        $lastFour = '****';

        if (is_object($paymentMethod) && method_exists($paymentMethod, 'lastFour')) {
            $lastFour = $paymentMethod->lastFour() ?? $lastFour;
        } else {
            $lastFour = data_get($paymentMethod, 'last_four')
                ?? data_get($paymentMethod, 'last_4')
                ?? data_get($paymentMethod, 'card_last_4')
                ?? data_get($paymentMethod, 'card_last4')
                ?? data_get($paymentMethod, 'last4')
                ?? $lastFour;
        }

        return $brand . ' **** ' . $lastFour;
    }
}
