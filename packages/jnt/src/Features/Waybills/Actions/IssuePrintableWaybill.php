<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Waybills\Actions;

use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class IssuePrintableWaybill implements PrintableWaybill
{
    public function __construct(private JntExpressService $carrier, private SignedDownloadApi $downloads) {}

    public function url(string $orderId, string | int | null $userId): ?string
    {
        if ($userId === null || (string) $userId === '') {
            throw new AuthorizationException('Printing a waybill requires an authenticated user.');
        }

        $order = (bool) config('jnt.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId)
            : JntOrder::query()->findOrFail($orderId);

        $result = $order->tracking_number !== null
            ? $this->carrier->printOrder($order->order_id, $order->tracking_number)
            : $this->carrier->printOrder($order->order_id);

        $waybill = PrintWaybillData::fromApiArray($result);
        $url = $waybill->urlContent;

        if ($waybill->hasUrlContent() && is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $url;
        }

        $content = $waybill->getPdfContent();

        if (! is_string($content) || ! str_starts_with($content, '%PDF-')) {
            return null;
        }

        return $this->downloads->issueUrl(
            cachePrefix: 'jnt_awb',
            routeName: 'jnt.awb.show',
            routeParameters: ['orderId' => $order->order_id],
            payload: [
                'content' => $content,
                'format' => 'pdf',
                'order_id' => $order->order_id,
                'tracking_number' => $order->tracking_number,
            ],
            ttl: 1800,
            owner: EloquentRecordReference::fromModel(OwnerContext::resolve()),
            userId: $userId,
        );
    }
}
