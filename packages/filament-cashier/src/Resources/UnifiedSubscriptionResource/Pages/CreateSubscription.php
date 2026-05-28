<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

final class CreateSubscription extends CreateRecord
{
    protected static string $resource = UnifiedSubscriptionResource::class;

    public function form(Schema $schema): Schema
    {
        $gatewayDetector = app(GatewayDetector::class);

        return $schema
            ->schema([
                Wizard::make([
                    Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.customer'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Select::make('billable_id')
                                ->label(__('filament-cashier::subscriptions.create.customer_label'))
                                ->options(fn () => $this->getCustomerOptions())
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
                                        ->options(fn (Get $get) => $this->getPlansForGateway($get('gateway')))
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
                                ->options(fn (Get $get) => $this->getPaymentMethodsForBillable($get('billable_id'), $get('gateway'))),
                        ]),
                ])
                    ->submitAction(new HtmlString(view('filament-cashier::components.wizard-submit-button')->render()))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getCustomerOptions(): array
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel) || ! is_a($billableModel, Model::class, true)) {
            return [];
        }

        return CashierOwnerScope::apply($billableModel::query())
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
    protected function getPlansForGateway(?string $gateway): array
    {
        if ($gateway === null) {
            return [];
        }

        // Try to get plans from config
        $configPlans = config("cashier.gateways.{$gateway}.plans", []);

        if (! empty($configPlans)) {
            return $configPlans;
        }

        // Return some common defaults for demo purposes
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
    protected function getPaymentMethodsForBillable(?string $billableId, ?string $gateway): array
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

        $billable = CashierOwnerScope::apply($billableModel::query())
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
                        $paymentMethodId = $this->getChipPaymentMethodId($paymentMethod);

                        if ($paymentMethodId === null) {
                            return [];
                        }

                        return [
                            $paymentMethodId => $this->getChipPaymentMethodLabel($paymentMethod),
                        ];
                    })
                    ->toArray();
            }
        } catch (Throwable) {
            // Silently fail if gateway API is not configured
        }

        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel) || ! is_a($billableModel, Model::class, true)) {
            throw new RuntimeException('Configured cashier billable model must be an Eloquent model.');
        }
        $billable = CashierOwnerScope::apply($billableModel::query())
            ->whereKey($data['billable_id'])
            ->first();

        if ($billable === null) {
            throw new AuthorizationException('Selected customer is not accessible.');
        }

        if (! $billable instanceof BillableContract) {
            throw new RuntimeException('Configured cashier billable model must implement BillableContract.');
        }

        $gateway = $data['gateway'];

        $gatewayDetector = app(GatewayDetector::class);

        if (! $gatewayDetector->isAvailable($gateway)) {
            throw new RuntimeException("Selected gateway is not available: {$gateway}");
        }

        $builder = Cashier::gateway($gateway)
            ->newSubscription($billable, $data['type'] ?? 'default', $data['plan_id']);

        if (isset($data['quantity']) && $data['quantity'] > 1) {
            $builder->quantity((int) $data['quantity']);
        }

        if (! empty($data['has_trial']) && ! empty($data['trial_days'])) {
            $builder->trialDays((int) $data['trial_days']);
        }

        if (! empty($data['payment_method']) && is_string($data['payment_method'])) {
            if (! $this->billableOwnsPaymentMethod($billable, $gateway, $data['payment_method'])) {
                throw new AuthorizationException('Selected payment method is not accessible.');
            }

            $builder->create($data['payment_method']);

            return $billable;
        }

        $builder->create();

        return $billable;
    }

    private function billableOwnsPaymentMethod(BillableContract $billable, string $gateway, string $paymentMethodId): bool
    {
        if ($gateway === 'stripe') {
            if (! method_exists($billable, 'paymentMethods')) {
                return false;
            }

            try {
                $methods = $billable->paymentMethods();

                if (! is_iterable($methods)) {
                    return false;
                }

                /** @var iterable<int, mixed> $methods */
                return collect($methods)->contains(
                    fn (mixed $paymentMethod): bool => (string) data_get($paymentMethod, 'id') === $paymentMethodId
                );
            } catch (Throwable) {
                return false;
            }
        }

        if ($gateway === 'chip') {
            if (! method_exists($billable, 'paymentMethods')) {
                return false;
            }

            try {
                $methods = $billable->paymentMethods();

                if (! is_iterable($methods)) {
                    return false;
                }

                /** @var iterable<int, mixed> $methods */
                return collect($methods)->contains(
                    fn (mixed $paymentMethod): bool => $this->getChipPaymentMethodId($paymentMethod) === $paymentMethodId
                );
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    private function getChipPaymentMethodId(mixed $paymentMethod): ?string
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

    private function getChipPaymentMethodLabel(mixed $paymentMethod): string
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

    protected function getCreatedNotification(): ?Notification
    {
        $gateway = $this->data['gateway'] ?? 'unknown';

        return Notification::make()
            ->title(__('filament-cashier::subscriptions.create.success', [
                'gateway' => ucfirst($gateway),
            ]))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
