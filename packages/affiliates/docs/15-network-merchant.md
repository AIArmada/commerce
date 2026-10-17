---
title: Network Merchant SDK
---

# Network Merchant SDK

Merchant stores that sell through an affiliate-network marketplace use two
small pieces instead of hand-rolled HTTP:

```php
use AIArmada\Affiliates\Merchant\CaptureNetworkReferral;
use AIArmada\Affiliates\Merchant\NetworkPostbackClient;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;
use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Carbon\CarbonImmutable;

// 1. Capture the ?anl= code in the web group (AppServiceProvider::boot)
$router->pushMiddlewareToGroup('web', CaptureNetworkReferral::class);

// 2. Read both touch instants when the paid order is reported
$sessionKey = (string) config('affiliates.merchant.session_key');
$linkCode = $request->session()->get($sessionKey);
$networkClickedAt = $request->session()->get($sessionKey . '_clicked_at');

if (is_string($linkCode) && is_string($networkClickedAt)) {
    $engineAttribution = app(RecordAffiliateConversion::class)->resolveAttributionFor($cart);
    $engineTouchAt = $engineAttribution?->last_referred_at;

    $result = app(NetworkPostbackClient::class)->report(
        $linkCode,
        (string) $order->number,
        (int) $order->total_minor,
        CarbonImmutable::parse($networkClickedAt),
        $engineTouchAt,
        (string) $order->currency,
    );

    $decision = $result['decision'];

    if ($result['ok'] && ($decision['winner'] ?? null) === 'network') {
        $engineConversion = AffiliateConversion::query()
            ->where('external_reference', (string) $order->number)
            ->first();

        if ($engineConversion instanceof AffiliateConversion) {
            VoidAffiliateConversion::run($engineConversion, 'network_attribution_won');
        }
    }
}
```

```env
AFFILIATE_NETWORK_URL=https://network.example
AFFILIATE_NETWORK_SITE=merchant.example
AFFILIATE_NETWORK_TOKEN=<site catalog token>
```

`CaptureNetworkReferral` stores the code at the configured session key
(`affiliate_network.link_code` by default) and the server-observed instant at
`{session_key}_clicked_at`. `report()` requires that click instant and accepts
the optional engine touch as a `CarbonImmutable`; pass `null` when the engine
has no attribution. It returns `['ok', 'status', 'body', 'decision', 'error']`,
where `decision` is `{winner, reason}` on a successful decision, and fails
closed when unconfigured. The referral query parameter (`anl`) and session
key are configurable under the `merchant` key, as is the endpoint prefix
(`prefix`, default `api/affiliate-network`) when the network customized
`affiliate-network.postbacks.prefix`.
Configure `connect_timeout_seconds` and `timeout_seconds` to bound network
requests. Reporting is idempotent on (network link, order reference) —
redeliveries are safe. Persist failed reports in the host application and
retry them with the same order reference; this client does not provide an
outbox.

The host engine and network are separate decision-makers for remote reports.
If the response says `winner: network`, do not keep the local engine conversion
payable: skip `RecordAffiliateConversion` if it has not run yet, or settle an
existing conversion with `VoidAffiliateConversion::run($conversion,
'network_attribution_won')`. The SDK does not suppress engine conversion
recording for the host.

When a reported order is later refunded, retract it on both sides: void the
local conversion as above and call
`voidConversion($linkCode, $orderReference, $reason)`, which posts to the
network's void endpoint and returns `['ok', 'status', 'body', 'leg_status',
'error']`. It is idempotent — retries after a timeout are safe, and a `404`
means the network never recorded that reference.

Pair this with a public program and a catalog provider (see
[Catalog](14-catalog.md)) so the marketplace can sync your offers, and read
the network-side contract in
[Merchant postbacks](../../affiliate-network/docs/10-merchant-postbacks.md).
