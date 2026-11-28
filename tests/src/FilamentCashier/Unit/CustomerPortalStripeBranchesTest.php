<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManagePaymentMethods;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\PaymentMethodsPreviewWidget;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\RecentInvoicesWidget;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

it('rejects non-billable models for invoice and payment method queries', function (): void {
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
    $authUser->exists = true;
    Auth::guard()->setUser($authUser);

    $viewInvoices = app(ViewInvoices::class);
    expect($viewInvoices->getInvoices())->toBeEmpty();

    $recentInvoices = app(RecentInvoicesWidget::class);
    expect($recentInvoices->getRecentInvoices())->toBeEmpty();

    $preview = app(PaymentMethodsPreviewWidget::class);
    expect($preview->getPaymentMethods())->toBeEmpty();

    $paymentMethodsPage = app(ManagePaymentMethods::class);
    expect($paymentMethodsPage->getPaymentMethods())->toBeEmpty();
    $paymentMethodsPage->setDefaultPaymentMethod('stripe', 'pm_1');
    $paymentMethodsPage->deletePaymentMethod('stripe', 'pm_1');
});

it('sorts customer portal invoices from the gateway contract by actual date descending', function (): void {
    $user = new ChipBillableUser;
    $user->forceFill(['id' => 124]);
    $user->exists = true;
    Auth::guard()->setUser($user);

    $old = Mockery::mock(InvoiceContract::class);
    $new = Mockery::mock(InvoiceContract::class);

    foreach ([[$old, 'in_old', '2025-01-31'], [$new, 'in_new', '2025-02-01']] as [$invoice, $id, $date]) {
        $invoice->shouldReceive('id')->andReturn($id);
        $invoice->shouldReceive('gateway')->andReturn('stripe');
        $invoice->shouldReceive('number')->andReturn($id);
        $invoice->shouldReceive('total')->andReturn('$10.00');
        $invoice->shouldReceive('date')->andReturn(Carbon::parse($date));
        $invoice->shouldReceive('status')->andReturn('paid');
        $invoice->shouldReceive('pdfUrl')->andReturn(null);
        $invoice->shouldReceive('hostedUrl')->andReturn(null);
    }

    $gateway = Mockery::mock(GatewayContract::class);
    $gateway->shouldReceive('invoices')->once()->with($user, ['limit' => 51])->andReturn(collect([$old, $new]));
    config()->set('cashier.gateways', ['stripe' => ['driver' => 'stripe']]);
    Cashier::shouldReceive('gateway')->once()->with('stripe')->andReturn($gateway);

    $invoices = app(ViewInvoices::class)->getInvoices();

    expect($invoices)->toHaveCount(2)
        ->and($invoices->first()['id'])->toBe('in_new')
        ->and($invoices->last()['id'])->toBe('in_old');
});
