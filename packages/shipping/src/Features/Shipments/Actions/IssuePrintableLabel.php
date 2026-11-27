<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Shipments\Actions;

use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Shipping\Features\Shipments\Api\PrintableLabel;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final readonly class IssuePrintableLabel implements PrintableLabel
{
    public function __construct(private ShippingManager $manager, private SignedDownloadApi $downloads) {}

    public function url(string $shipmentId, string | int | null $userId): ?string
    {
        if ($userId === null || (string) $userId === '') {
            throw new AuthorizationException('Printing a shipping label requires an authenticated user.');
        }

        $shipment = (bool) config('shipping.features.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(Shipment::class, $shipmentId)
            : Shipment::query()->findOrFail($shipmentId);

        if ($shipment->tracking_number === null) {
            return null;
        }

        $label = $this->manager->driver($shipment->carrier_code)->generateLabel($shipment->tracking_number, [
            'order_id' => $shipment->reference,
        ]);

        if ($label->hasUrl()) {
            $url = $label->url;

            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                throw new RuntimeException('The carrier returned an invalid label URL.');
            }

            return $url;
        }

        $content = $label->getDecodedContent();

        if ($content === null || $content === '' || ! in_array($label->format, ['pdf', 'png', 'zpl'], true)) {
            return null;
        }

        return $this->downloads->issueUrl(
            cachePrefix: 'shipping_label',
            routeName: 'shipping.labels.show',
            routeParameters: ['trackingNumber' => $shipment->tracking_number],
            payload: [
                'content' => $content,
                'format' => $label->format,
                'tracking_number' => $shipment->tracking_number,
            ],
            ttl: 1800,
            owner: EloquentRecordReference::fromModel(OwnerContext::resolve()),
            userId: $userId,
        );
    }
}
