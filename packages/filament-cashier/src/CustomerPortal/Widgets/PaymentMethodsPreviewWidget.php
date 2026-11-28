<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\Cashier\Queries\CustomerPaymentMethodsQuery;
use Illuminate\Database\Eloquent\Model;
use Filament\Widgets\Widget;

final class PaymentMethodsPreviewWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-cashier::customer-portal.widgets.payment-methods-preview';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 2;

    /**
     * @return array<string, array{type: string, last4: string, is_default: bool}>
     */
    public function getPaymentMethods(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        return $user instanceof Model ? app(CustomerPaymentMethodsQuery::class)->defaults($user) : [];
    }
}
