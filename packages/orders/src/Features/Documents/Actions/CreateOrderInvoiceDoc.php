<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents\Actions;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Orders\Features\Documents\Actions\Concerns\BuildsOrderDocs;
use AIArmada\Orders\Models\Order;

final readonly class CreateOrderInvoiceDoc
{
    use BuildsOrderDocs;

    public function __construct(
        private DocServiceInterface $docService
    ) {}

    public function execute(Order $order, string $transactionId, string $gateway): ?Doc
    {
        return $this->runWithinOrderOwnerScope($order, function () use ($order, $transactionId, $gateway): ?Doc {
            return $order->getConnection()->transaction(function () use ($order, $transactionId, $gateway): ?Doc {
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
