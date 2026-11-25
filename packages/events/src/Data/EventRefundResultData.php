<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventRefundResultData
{
    public function __construct(
        public string $status,
        public ?string $transactionId = null,
        public ?string $paymentId = null,
        public ?string $message = null,
        public ?string $refundId = null,
    ) {}
}
