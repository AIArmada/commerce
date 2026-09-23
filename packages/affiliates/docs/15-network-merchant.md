---
title: Network Merchant SDK
---

# Network Merchant SDK

Merchant stores that sell through an affiliate-network marketplace use two
small pieces instead of hand-rolled HTTP:

```php
use AIArmada\Affiliates\Merchant\CaptureNetworkReferral;
use AIArmada\Affiliates\Merchant\NetworkPostbackClient;

// 1. Capture the ?anl= code in the web group (AppServiceProvider::boot)
$router->pushMiddlewareToGroup('web', CaptureNetworkReferral::class);

// 2. Report the paid order from checkout
$result = app(NetworkPostbackClient::class)->report(
    $request->session()->get(config('affiliates.merchant.session_key')),
    $order->number,
    $order->total_minor,
    $order->currency,
);
```

```env
AFFILIATE_NETWORK_URL=https://network.example
AFFILIATE_NETWORK_SITE=merchant.example
AFFILIATE_NETWORK_TOKEN=<site catalog token>
```

`report()` returns `['ok', 'status', 'body', 'error']` and fails closed when
unconfigured. The referral query parameter (`anl`) and session key
(`affiliate_network.link_code`) are configurable under the `merchant` key,
as is the endpoint prefix (`prefix`, default `api/affiliate-network`) when
the network customized `affiliate-network.postbacks.prefix`.
Reporting is idempotent on (network link, order reference) — redeliveries
are safe.

Pair this with a public program and a catalog provider (see
[Catalog](14-catalog.md)) so the marketplace can sync your offers, and read
the network-side contract in
[Merchant postbacks](../../affiliate-network/docs/10-merchant-postbacks.md).
