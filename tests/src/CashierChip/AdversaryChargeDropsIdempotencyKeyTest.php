<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\ChargeChipCustomer;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

uses(CashierChipTestCase::class);

/**
 * ADVERSARY PROOF — Attack 1 sibling: `ChargeChipCustomer` silently drops
 * the `idempotency_key` option.
 *
 * The billable `PerformsCharges::charge()` path honors `idempotency_key`
 * (via `applyIdempotencyKey()`); the `ChargeChipCustomer` Action — the exact
 * path `RenewSubscriptionsCommand::executeAttempt()` uses — never forwards
 * it, so only the `reference` fallback (or nothing) protects the post. A
 * retry that reuses the documented key but varies the reference (or passes
 * none) double-posts. Renewal retries after `TRANSPORT_OUTCOME_UNKNOWN`
 * take this path with `purchase_id` still null.
 *
 * Mock seam: HTTP client only (`ChipCollectClient`) inside a REAL
 * `ChipCollectService`/`PurchasesApi` with the real array cache. The
 * cashier-chip Action under test is unmocked.
 */
function adversaryActionPurchaseResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'purchase_action_1',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
        'client' => ['email' => 'billable@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'total' => 1000,
            'products' => [[
                'name' => 'One-time charge',
                'price' => 1000,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'test_brand_id',
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'created',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'refund_availability' => 'all',
        'refundable_amount' => 1000,
        'payment_method_whitelist' => [],
    ], $overrides);
}

it('honours the idempotency_key option instead of posting twice', function (): void {
    Cashier::unfake();

    $client = Mockery::mock(ChipCollectClient::class);
    $posts = 0;
    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/', Mockery::any())
        ->andReturnUsing(function () use (&$posts): array {
            $posts++;

            return adversaryActionPurchaseResponse();
        });

    $cache = Cache::store('array');
    $cache->clear();
    app()->instance(ChipCollectService::class, new ChipCollectService($client, $cache));

    $billable = new class extends Model
    {
        public function chipId(): ?string
        {
            return 'chip_cus_action_1';
        }

        public function hasChipId(): bool
        {
            return true;
        }

        public function chipEmail(): ?string
        {
            return 'billable@example.com';
        }

        public function chipName(): ?string
        {
            return 'Action Billable';
        }

        public function preferredCurrency(): string
        {
            return 'MYR';
        }

        public function getMorphClass(): string
        {
            return 'action-billable';
        }

        public function getKey(): mixed
        {
            return 'action-billable-1';
        }
    };

    $options = ['idempotency_key' => 'adversary-action-key-1', 'currency' => 'MYR'];

    ChargeChipCustomer::run($billable, 1000, null, $options);
    ChargeChipCustomer::run($billable, 1000, null, $options);

    expect($posts)->toBe(1, 'ChargeChipCustomer ignored idempotency_key and posted the purchase twice.');
});
