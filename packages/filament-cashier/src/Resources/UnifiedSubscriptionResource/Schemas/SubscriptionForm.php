<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Schemas;

use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Queries\CustomerPaymentMethodsQuery;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
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
        $billableModel = (string) config('cashier.models.billable');

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

        return app(GatewayDetector::class)->isAvailable($gateway)
            ? (array) config("cashier.gateways.{$gateway}.plans", [])
            : [];
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

        $billableModel = (string) config('cashier.models.billable');

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
            return app(CustomerPaymentMethodsQuery::class)->records($billable, $gateway)
                ->mapWithKeys(static fn (PaymentMethodContract $method): array => [
                    $method->id() => ($method->brand() ?? ($method->type() ?: 'Card')) . ' **** ' . ($method->lastFour() ?? '****'),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
