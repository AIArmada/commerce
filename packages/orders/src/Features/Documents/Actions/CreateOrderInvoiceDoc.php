<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents\Actions;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Data\DocType;
use AIArmada\Orders\Features\Documents\Actions\Concerns\BuildsOrderDocs;
use AIArmada\Orders\Models\Order;

final readonly class CreateOrderInvoiceDoc
{
    use BuildsOrderDocs;

    public function __construct(
        private DocServiceInterface $docService
    ) {}

    public function execute(Order $order, string $transactionId, string $gateway): ?string
    {
        return $this->runWithinOrderOwnerScope($order, function () use ($order, $transactionId, $gateway): ?string {
            return $order->getConnection()->transaction(function () use ($order, $transactionId, $gateway): ?string {
                $order = $order->newQuery()->lockForUpdate()->findOrFail($order->getKey());

                if ($this->findExistingDoc($order, DocType::Invoice) !== null) {
                    return null;
                }

                return $this->docService->create($this->buildDocData(
                    order: $order,
                    docType: DocType::Invoice,
                    transactionId: $transactionId,
                    gateway: $gateway,
                    generatePdf: (bool) config('orders.integrations.docs.generate_pdf', false),
                ));
            });
        });
    }
}
