<?php

declare(strict_types=1);

namespace AIArmada\Orders\Actions;

use AIArmada\Docs\Enums\DocType;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Models\Order;

final class DetermineOrderDocumentType
{
    public function execute(Order $order): DocType
    {
        if ($order->isPaid() || $order->payments()->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Refunded->value])->exists()) {
            return DocType::Receipt;
        }

        return DocType::Invoice;
    }
}
