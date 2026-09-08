<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Illuminate\Support\Facades\Cache;

/**
 * ADVERSARY PROOF — Attack 1 sibling: recurring-token `charge()` has no
 * idempotency at all.
 *
 * `PurchasesApi::charge()` posts `purchases/{id}/charge/` with the recurring
 * token and accepts no idempotency key, writes no cache entry, and takes no
 * lock. Any retry after an ambiguous outcome (gateway timeout, worker retry,
 * crash after the gateway charged but before the response was recorded)
 * charges the same purchase twice. Same shape: `capture()`, `refund()`,
 * `markAsPaid()`, `release()`, `cancel()`, `resendInvoice()` are equally
 * keyless — `charge()` is proven here as the money-moving representative.
 *
 * Mock seam: HTTP client only (`ChipCollectClient`).
 */

function adversaryChargePurchaseResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'purchase_charge_1',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'total' => 1000,
            'products' => [[
                'name' => 'Subscription renewal',
                'price' => 1000,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'brand_123',
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'paid',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'refund_availability' => 'all',
        'refundable_amount' => 0,
        'payment_method_whitelist' => [],
    ], $overrides);
}

it('does not re-charge a purchase when a recurring-token charge is retried', function (): void {
    $client = Mockery::mock(ChipCollectClient::class);
    $chargePosts = 0;

    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/purchase_charge_1/charge/', Mockery::any())
        ->andReturnUsing(function () use (&$chargePosts): array {
            $chargePosts++;

            return adversaryChargePurchaseResponse();
        });

    $api = new PurchasesApi(Cache::store('array'), $client);

    // First attempt charges at the gateway; the response is then lost
    // (timeout / crash / worker retry) so the caller retries identically.
    $api->charge('purchase_charge_1', 'recurring_token_abc');
    $api->charge('purchase_charge_1', 'recurring_token_abc');

    expect($chargePosts)->toBe(1, 'Retried recurring-token charge posted twice: the purchase was charged twice.');
});
