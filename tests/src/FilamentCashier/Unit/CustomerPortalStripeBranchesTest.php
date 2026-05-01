<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\CustomerPortal\Pages\ManagePaymentMethods;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\PaymentMethodsPreviewWidget;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\RecentInvoicesWidget;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

it('covers stripe branches in customer portal invoices and payment methods via a fake gateway detector', function (): void {
    app()->bind(GatewayDetector::class, function (): object {
        return new class
        {
            public function isAvailable(string $gateway): bool
            {
                return in_array($gateway, ['stripe', 'chip'], true);
            }

            public function availableGateways(): Collection
            {
                return collect(['stripe', 'chip']);
            }

            public function getGatewayOptions(): array
            {
                return ['stripe' => 'Stripe', 'chip' => 'CHIP'];
            }

            public function getLabel(string $gateway): string
            {
                return $gateway === 'stripe' ? 'Stripe' : 'CHIP';
            }

            public function getColor(string $gateway): string
            {
                return $gateway === 'stripe' ? 'info' : 'warning';
            }

            public function getIcon(string $gateway): string
            {
                return 'heroicon-o-credit-card';
            }
        };
    });

    $stripeInvoice = new class
    {
        public string $id = 'in_1';

        public ?string $number = 'INV-STRIPE-1';

        public bool $paid = true;

        public function total(): string
        {
            return '$12.99';
        }

        public function date(): Carbon
        {
            return Carbon::parse('2025-01-03 00:00:00');
        }

        public function invoicePdf(): string
        {
            return 'https://example.test/invoices/in_1.pdf';
        }
    };

    $stripePaymentMethods = collect([
        (object) ['id' => 'pm_1', 'card' => (object) ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]],
    ]);

    $authUser = new class extends AuthenticatableUser
    {
        protected $guarded = [];

        public object $invoice;

        public Collection $paymentMethods;

        public function invoices(array $options = []): array
        {
            return [$this->invoice];
        }

        public function paymentMethods(): Collection
        {
            return $this->paymentMethods;
        }

        public function defaultPaymentMethod(): object
        {
            return (object) ['id' => 'pm_1'];
        }

        public function updateDefaultPaymentMethod(string $paymentMethodId): void {}

        public function findPaymentMethod(string $paymentMethodId): ?object
        {
            return new class
            {
                public function delete(): void {}
            };
        }
    };

    $authUser->invoice = $stripeInvoice;
    $authUser->paymentMethods = $stripePaymentMethods;
    $authUser->forceFill(['id' => 123]);
    Auth::guard()->setUser($authUser);

    $viewInvoices = app(ViewInvoices::class);
    expect($viewInvoices->getInvoices())->toHaveCount(1);

    $recentInvoices = app(RecentInvoicesWidget::class);
    expect($recentInvoices->getRecentInvoices())->toHaveCount(1);

    $preview = app(PaymentMethodsPreviewWidget::class);
    expect($preview->getPaymentMethods())->toHaveKey('stripe');

    $paymentMethodsPage = app(ManagePaymentMethods::class);
    expect($paymentMethodsPage->getPaymentMethods())->toHaveKey('stripe');
    $paymentMethodsPage->setDefaultPaymentMethod('stripe', 'pm_1');
    $paymentMethodsPage->deletePaymentMethod('stripe', 'pm_1');
});

it('sorts customer portal invoices by actual invoice date descending', function (): void {
    app()->bind(GatewayDetector::class, function (): object {
        return new class
        {
            public function isAvailable(string $gateway): bool
            {
                return $gateway === 'stripe';
            }

            public function availableGateways(): Collection
            {
                return collect(['stripe']);
            }

            public function getGatewayOptions(): array
            {
                return ['stripe' => 'Stripe'];
            }

            public function getLabel(string $gateway): string
            {
                return 'Stripe';
            }

            public function getColor(string $gateway): string
            {
                return 'info';
            }

            public function getIcon(string $gateway): string
            {
                return 'heroicon-o-credit-card';
            }
        };
    });

    $olderInvoice = new class
    {
        public string $id = 'in_old';

        public ?string $number = 'INV-OLD';

        public bool $paid = true;

        public function total(): string
        {
            return '$10.00';
        }

        public function date(): Carbon
        {
            return Carbon::parse('2025-01-31 00:00:00');
        }

        public function invoicePdf(): string
        {
            return 'https://example.test/invoices/in_old.pdf';
        }
    };

    $newerInvoice = new class
    {
        public string $id = 'in_new';

        public ?string $number = 'INV-NEW';

        public bool $paid = true;

        public function total(): string
        {
            return '$20.00';
        }

        public function date(): Carbon
        {
            return Carbon::parse('2025-02-01 00:00:00');
        }

        public function invoicePdf(): string
        {
            return 'https://example.test/invoices/in_new.pdf';
        }
    };

    $authUser = new class extends AuthenticatableUser
    {
        protected $guarded = [];

        public object $older;

        public object $newer;

        public function invoices(array $options = []): array
        {
            return [$this->older, $this->newer];
        }
    };

    $authUser->older = $olderInvoice;
    $authUser->newer = $newerInvoice;
    $authUser->forceFill(['id' => 124]);
    Auth::guard()->setUser($authUser);

    $invoices = app(ViewInvoices::class)->getInvoices();

    expect($invoices)->toHaveCount(2)
        ->and($invoices->first()['id'])->toBe('in_new')
        ->and($invoices->last()['id'])->toBe('in_old');
});
