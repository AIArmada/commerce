<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Waybills\Actions;

use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\AbsoluteHttpUrl;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\FileSignature;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Features\Orders\Api\ReadWaybillOrder;
use AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final readonly class IssuePrintableWaybill implements PrintableWaybill
{
    public function __construct(private JntExpressService $carrier, private SignedDownloadApi $downloads, private ReadWaybillOrder $orders) {}

    public function url(string $orderId, string | int | null $userId): ?string
    {
        if ($userId === null || (string) $userId === '') {
            throw new AuthorizationException('Printing a waybill requires an authenticated user.');
        }

        $order = $this->orders->find($orderId);

        $result = $order->trackingNumber !== null
            ? $this->carrier->printOrder($order->orderId, $order->trackingNumber)
            : $this->carrier->printOrder($order->orderId);

        try {
            $waybill = PrintWaybillData::fromApiArray($result);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($waybill->orderId !== '' && $waybill->orderId !== $order->orderId) {
            return null;
        }

        $content = $waybill->getPdfContent();

        if ($waybill->hasBase64Content()) {
            if (! FileSignature::isPdf($content)) {
                return null;
            }
        } else {
            $url = AbsoluteHttpUrl::normalize($waybill->getDownloadUrl());

            if ($url === null) {
                return null;
            }

            return $url;
        }

        return $this->downloads->issueUrl(
            cachePrefix: 'jnt_awb',
            routeName: 'jnt.awb.show',
            routeParameters: ['orderId' => $order->orderId],
            payload: [
                'content' => $content,
                'format' => 'pdf',
                'order_id' => $order->orderId,
                'tracking_number' => $order->trackingNumber,
            ],
            ttl: 1800,
            owner: EloquentRecordReference::fromModel(OwnerContext::resolve()),
            userId: $userId,
        );
    }
}
