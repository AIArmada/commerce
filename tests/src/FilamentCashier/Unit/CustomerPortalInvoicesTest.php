<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManagePaymentMethods;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\PaymentMethodsPreviewWidget;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\RecentInvoicesWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

it('formats and returns customer portal invoices and payment methods when CHIP is available', function (): void {
    $user = ChipBillableUser::query()->create([
        'name' => 'Portal User',
        'email' => 'portal-invoices@example.com',
        'password' => bcrypt('secret'),
    ]);

    Auth::guard()->setUser($user);

    $page = app(ViewInvoices::class);
    $invoices = $page->getInvoices();
    expect($invoices)->toHaveCount(2);
    expect($invoices->first())->toHaveKeys(['id', 'gateway', 'number', 'amount', 'date', 'status', 'download_url']);

    $recentWidget = app(RecentInvoicesWidget::class);
    $recent = $recentWidget->getRecentInvoices();
    expect($recent)->toHaveCount(2);
    expect($recent->first())->toHaveKeys(['id', 'gateway', 'amount', 'date', 'status']);

    $previewWidget = app(PaymentMethodsPreviewWidget::class);
    expect($previewWidget->getPaymentMethods())->toHaveKey('chip');

    $paymentMethods = app(ManagePaymentMethods::class);
    expect($paymentMethods->getPaymentMethods())->toHaveKey('chip');
    $paymentMethods->setDefaultPaymentMethod('chip', 'chip_pm_2');
    $paymentMethods->deletePaymentMethod('chip', 'chip_pm_2');
});

it('handles customer portal payment method failures gracefully', function (): void {
    $failingUser = new class extends ChipBillableUser
    {
        public function updateDefaultChipPaymentMethod(string $paymentMethodId): void
        {
            throw new RuntimeException('Boom');
        }

        public function deleteChipPaymentMethod(string $paymentMethodId): void
        {
            throw new RuntimeException('Boom');
        }
    };

    $failingUser->forceFill(['id' => 999, 'name' => 'Failing', 'email' => 'failing@example.com', 'password' => bcrypt('secret')]);

    Auth::guard()->setUser($failingUser);

    $page = app(ManagePaymentMethods::class);
    expect($page->getPaymentMethods())->toHaveKey('chip');
    $page->setDefaultPaymentMethod('chip', 'chip_pm_1');
    $page->deletePaymentMethod('chip', 'chip_pm_1');
});

it('fails closed for non-owned payment method ids in customer portal mutations', function (): void {
    $user = new class extends ChipBillableUser
    {
        public bool $setDefaultCalled = false;

        public bool $deleteCalled = false;

        public function chipPaymentMethods(): Collection
        {
            return collect([
                (object) ['id' => 'chip_pm_1', 'type' => 'card', 'last4' => '1111', 'is_default' => true],
            ]);
        }

        public function updateDefaultChipPaymentMethod(string $paymentMethodId): void
        {
            $this->setDefaultCalled = true;
        }

        public function deleteChipPaymentMethod(string $paymentMethodId): void
        {
            $this->deleteCalled = true;
        }
    };

    $user->forceFill(['id' => 1001, 'name' => 'Owned PM User', 'email' => 'owned-pm@example.com', 'password' => bcrypt('secret')]);

    Auth::guard()->setUser($user);

    $page = app(ManagePaymentMethods::class);
    $page->setDefaultPaymentMethod('chip', 'chip_pm_not_owned');
    $page->deletePaymentMethod('chip', 'chip_pm_not_owned');

    expect($user->setDefaultCalled)->toBeFalse()
        ->and($user->deleteCalled)->toBeFalse();
});
