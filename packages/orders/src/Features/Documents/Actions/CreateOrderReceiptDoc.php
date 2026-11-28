<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents\Actions;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Orders\Features\Documents\Actions\Concerns\BuildsOrderDocs;
use AIArmada\Orders\Models\Order;

final readonly class CreateOrderReceiptDoc
{
    use BuildsOrderDocs;

    public function __construct(
        private DocServiceInterface $docService,
    ) {}

    public function execute(Order $order, string $transactionId, string $gateway): Doc
    {
        return $this->runWithinOrderOwnerScope($order, function () use ($order, $transactionId, $gateway): Doc {
            return $order->getConnection()->transaction(function () use ($order, $transactionId, $gateway): Doc {
                $order = $order->newQuery()->lockForUpdate()->findOrFail($order->getKey());

                $existingReceipt = $this->findExistingDoc($order, DocType::Receipt);

                if ($existingReceipt instanceof Doc) {
                    return $existingReceipt;
                }

                return $this->docService->create($this->buildDocData(
                    order: $order,
                    docType: DocType::Receipt,
                    transactionId: $transactionId,
                    gateway: $gateway,
                    metadata: [
                        'generated_by' => static::class,
                    ],
                ));
            });
        });
    }
}
