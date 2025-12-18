<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManagePaymentMethods;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\PaymentMethodsPreviewWidget;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\RecentInvoicesWidget;
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
