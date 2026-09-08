<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Concerns\PerformsCharges;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

uses(CashierChipTestCase::class);

/**
 * ADVERSARY PROOF — Attack 1 sibling: two-step billable charge re-fires the
 * recurring-token charge on retry.
 *
 * `PerformsCharges::charge()` with a recurring token performs TWO remote
 * writes: an idempotent purchase create, then `chargePurchase()` — which
 * accepts no key and records nothing. The create replays from cache on
 * retry, but the token charge posts again against the same purchase, so a
 * retry after an ambiguous charge outcome double-charges. This is the same
 * hole as the chip-level `charge()` proof, reached through the billable
 * charge path that renewals use.
 *
 * Mock seam: HTTP client only (`ChipCollectClient`) inside a REAL
 * `ChipCollectService`/`PurchasesApi` with the real array cache.
 */

function adversaryTwoStepCreatedResponse(): array
{
    return [
        'id' => 'purchase_twostep_1',
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
    ];
}

function adversaryTwoStepChargedResponse(): array
{
    $response = adversaryTwoStepCreatedResponse();
    $response['status'] = 'paid';
    $response['refundable_amount'] = 0;

    return $response;
}

it('does not re-fire the recurring-token charge when a token charge is retried', function (): void {
    Cashier::unfake();

    $client = Mockery::mock(ChipCollectClient::class);
    $creates = 0;
    $charges = 0;

    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/', Mockery::any())
        ->andReturnUsing(function () use (&$creates): array {
            $creates++;

            return adversaryTwoStepCreatedResponse();
        });
    $client->shouldReceive('post')
        ->zeroOrMoreTimes()
        ->with('purchases/purchase_twostep_1/charge/', Mockery::any())
        ->andReturnUsing(function () use (&$charges): array {
            $charges++;

            return adversaryTwoStepChargedResponse();
        });

    $cache = Cache::store('array');
    $cache->clear();
    app()->instance(ChipCollectService::class, new ChipCollectService($client, $cache));

    $billable = new class extends Model
    {
        use PerformsCharges;

        public function chipId(): ?string
        {
            return 'chip_cus_twostep_1';
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
            return 'Twostep Billable';
        }

        public function preferredCurrency(): string
        {
            return 'MYR';
        }

        public function getMorphClass(): string
        {
            return 'twostep-billable';
        }

        public function getKey(): mixed
        {
            return 'twostep-billable-1';
        }
    };

    $options = ['idempotency_key' => 'adversary-twostep-key-1', 'currency' => 'MYR'];

    // First attempt: create + token charge both succeed; the outcome is then
    // lost (timeout / crash), so the caller retries with identical arguments.
    $billable->charge(1000, 'recurring_token_twostep', $options);
    $billable->charge(1000, 'recurring_token_twostep', $options);

    expect($creates)->toBe(1)
        ->and($charges)->toBe(1, 'Retried token charge re-fired the recurring-token charge on the same purchase.');
});
