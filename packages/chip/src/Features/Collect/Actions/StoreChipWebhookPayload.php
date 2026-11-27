<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Contracts\PurchaseStatus;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Collect\Api\PurchaseMirror;
use AIArmada\Chip\Features\Collect\Models\Client;
use AIArmada\Chip\Features\Collect\Models\Payment;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class StoreChipWebhookPayload implements PurchaseMirror
{
    /**
     * Store a purchase resource returned by the CHIP API.
     *
     * API synchronisation is not a webhook delivery, so it must not invent an
     * event type merely to reuse the webhook event object.
     *
     * @param  array<string, mixed>  $payload
     */
    public function storePurchasePayload(array $payload): void
    {
        if (($payload['type'] ?? null) !== 'purchase' || empty($payload['id'])) {
            Log::warning('CHIP: Purchase resource must contain a type and ID');

            return;
        }

        DB::transaction(function () use ($payload): void {
            if ($this->storePurchase($payload)) {
                $this->storeClient($payload);
                $this->storePayment($payload);
            }
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * CHIP guarantees purchase IDs are globally unique UUIDs across brands/tenants.
     * We therefore upsert purchases by `id` only and then apply owner attribution.
     */
    private function storePurchase(array $payload): bool
    {
        $owner = $this->resolveOwner();

        $purchase = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($payload['id'])->lockForUpdate()->first();

        if ($purchase === null) {
            $purchase = new Purchase;
        }

        if ($purchase->exists && (string) $purchase->getKey() === (string) $payload['id']) {
            $previousUpdate = $purchase->getRawOriginal('updated_on');
            $incomingUpdate = $payload['updated_on'] ?? null;

            if (is_numeric($previousUpdate) && (! is_numeric($incomingUpdate) || (int) $incomingUpdate < (int) $previousUpdate)) {
                return false;
            }

            $previousStatus = PurchaseStatus::tryFrom((string) $purchase->status);
            $incomingStatus = PurchaseStatus::tryFrom((string) ($payload['status'] ?? ''));

            if (in_array($previousStatus, [PurchaseStatus::REFUNDED, PurchaseStatus::CHARGEBACK], true)
                && ! in_array($incomingStatus, [PurchaseStatus::REFUNDED, PurchaseStatus::CHARGEBACK], true)) {
                return false;
            }

            if ($previousStatus !== null && $incomingStatus !== null
                && ($previousStatus->isSuccessful() || in_array($previousStatus, [PurchaseStatus::REFUNDED, PurchaseStatus::CHARGEBACK], true))
                && ($incomingStatus->isFailed() || ($incomingStatus->isPending() && $incomingStatus !== PurchaseStatus::PENDING_REFUND) || $incomingStatus === PurchaseStatus::RELEASED)) {
                return false;
            }
        }

        $purchase->forceFill(
            [
                'id' => $payload['id'],
                'metadata' => $this->mergeLedgerMetadata($purchase, $payload),

                // Core fields
                'type' => $payload['type'],
                'created_on' => $payload['created_on'],
                'updated_on' => $payload['updated_on'],

                // JSON objects
                'client' => $payload['client'] ?? [],
                'purchase' => $payload['purchase'] ?? [],
                'payment' => $payload['payment'] ?? null,
                'issuer_details' => $payload['issuer_details'] ?? [],
                'transaction_data' => $payload['transaction_data'] ?? [],
                'status_history' => $payload['status_history'] ?? [],
                'currency_conversion' => $payload['currency_conversion'] ?? null,
                'payment_method_whitelist' => $payload['payment_method_whitelist'] ?? null,

                // UUID references
                'brand_id' => $payload['brand_id'],
                'company_id' => $payload['company_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'billing_template_id' => $payload['billing_template_id'] ?? null,
                'client_id' => $payload['client_id'] ?? null,

                // Status
                'status' => is_string($payload['status'] ?? null) ? $payload['status'] : $purchase->getAttribute('status'),
                'total_minor' => $this->resolveTotalMinor($payload, $purchase),
                'payment_method' => $this->resolvePaymentMethod($payload, $purchase),
                'viewed_on' => $payload['viewed_on'] ?? null,

                // Flags
                'send_receipt' => $payload['send_receipt'] ?? false,
                'is_test' => $payload['is_test'] ?? false,
                'is_recurring_token' => $payload['is_recurring_token'] ?? false,
                'recurring_token' => $payload['recurring_token'] ?? null,
                'skip_capture' => $payload['skip_capture'] ?? false,
                'force_recurring' => $payload['force_recurring'] ?? false,
                'marked_as_paid' => $payload['marked_as_paid'] ?? false,

                // References
                'reference' => $payload['reference'] ?? null,
                'reference_generated' => $payload['reference_generated'] ?? null,
                'notes' => $payload['purchase']['notes'] ?? null,
                'issued' => $payload['issued'] ?? null,
                'due' => $payload['due'] ?? null,
                'order_id' => $payload['order_id'] ?? null,

                // Refund
                'refund_availability' => $payload['refund_availability'] ?? 'all',
                'refundable_amount' => $payload['refundable_amount'] ?? 0,

                // URLs
                'success_redirect' => $payload['success_redirect'] ?? null,
                'failure_redirect' => $payload['failure_redirect'] ?? null,
                'cancel_redirect' => $payload['cancel_redirect'] ?? null,
                'success_callback' => $payload['success_callback'] ?? null,
                'invoice_url' => $payload['invoice_url'] ?? null,
                'checkout_url' => $payload['checkout_url'] ?? null,
                'direct_post_url' => $payload['direct_post_url'] ?? null,

                // Platform
                'creator_agent' => $payload['creator_agent'] ?? null,
                'platform' => $payload['platform'] ?? null,
                'product' => $payload['product'] ?? null,
                'created_from_ip' => $payload['created_from_ip'] ?? null,
            ]
        );
        if ($owner !== null && ! $purchase->hasOwner()) {
            $purchase->assignOwner($owner);
        }

        $purchase->save();

        return true;
    }

    /**
     * Merge incoming API metadata with the local idempotency ledger entry.
     *
     * Syncs must not wipe the ledger: replayed creates look it up by key.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function mergeLedgerMetadata(Purchase $purchase, array $payload): ?array
    {
        $incoming = $payload['purchase']['metadata'] ?? null;
        $incoming = is_array($incoming) ? $incoming : null;

        if ($incoming !== null) {
            unset($incoming['chip_idempotency']);
        }

        $existing = $purchase->metadata ?? null;
        $ledger = is_array($existing) ? ($existing['chip_idempotency'] ?? null) : null;

        if ($ledger === null) {
            return $incoming;
        }

        return array_merge($incoming ?? [], ['chip_idempotency' => $ledger]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeClient(array $payload): void
    {
        $owner = $this->resolveOwner();

        $clientId = $payload['client_id'] ?? null;
        $clientData = $payload['client'] ?? null;

        if (! $clientId || ! is_array($clientData) || empty($clientData['email'])) {
            return;
        }

        $client = Client::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($clientId)->lockForUpdate()->first() ?? new Client(['id' => $clientId]);

        $previousUpdate = $client->getRawOriginal('updated_on');
        if ($client->exists && is_numeric($previousUpdate) && (int) $payload['updated_on'] < (int) $previousUpdate) {
            return;
        }
        $client->forceFill(
            [
                'type' => 'client',
                'created_on' => $payload['created_on'],
                'updated_on' => $payload['updated_on'],
                'email' => $clientData['email'],
                'phone' => $clientData['phone'] ?? null,
                'full_name' => $clientData['full_name'] ?? null,
                'personal_code' => $clientData['personal_code'] ?? null,
                'street_address' => $clientData['street_address'] ?? null,
                'city' => $clientData['city'] ?? null,
                'zip_code' => $clientData['zip_code'] ?? null,
                'state' => $clientData['state'] ?? null,
                'country' => $clientData['country'] ?? null,
                'shipping_street_address' => $clientData['shipping_street_address'] ?? null,
                'shipping_city' => $clientData['shipping_city'] ?? null,
                'shipping_zip_code' => $clientData['shipping_zip_code'] ?? null,
                'shipping_state' => $clientData['shipping_state'] ?? null,
                'shipping_country' => $clientData['shipping_country'] ?? null,
                'cc' => $clientData['cc'] ?? null,
                'bcc' => $clientData['bcc'] ?? null,
                'legal_name' => $clientData['legal_name'] ?? null,
                'brand_name' => $clientData['brand_name'] ?? null,
                'registration_number' => $clientData['registration_number'] ?? null,
                'tax_number' => $clientData['tax_number'] ?? null,
                'bank_account' => $clientData['bank_account'] ?? null,
                'bank_code' => $clientData['bank_code'] ?? null,
            ]
        );

        if ($owner !== null && ! $client->hasOwner()) {
            $client->assignOwner($owner);
        }

        $client->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storePayment(array $payload): void
    {
        $owner = $this->resolveOwner();

        $purchaseId = $payload['id'] ?? null;
        $paymentData = $payload['payment'] ?? null;
        $paymentId = null;

        if (($payload['type'] ?? null) === 'payment') {
            $purchaseId = data_get($payload, 'related_to.id');
            $paymentData = isset($payload['payment']) && is_array($payload['payment']) ? $payload['payment'] : $payload;
            $paymentId = is_string($payload['id'] ?? null) && $payload['id'] !== '' ? $payload['id'] : null;
        } elseif (is_string($paymentData['id'] ?? null) && $paymentData['id'] !== '') {
            $paymentId = $paymentData['id'];
        }

        if (! is_string($purchaseId) || $purchaseId === '' || ! is_array($paymentData) || ! isset($paymentData['amount'])) {
            return;
        }

        $purchaseCurrency = is_array($payload['purchase'] ?? null) ? ($payload['purchase']['currency'] ?? null) : null;
        $currency = $paymentData['currency'] ?? $purchaseCurrency;

        if (! is_string($currency) || $currency === '') {
            return;
        }

        $paymentType = is_string($paymentData['payment_type'] ?? null) ? $paymentData['payment_type'] : 'purchase';

        $paymentMatch = $paymentId !== null
            ? ['id' => $paymentId]
            : [
                'purchase_id' => $purchaseId,
                'payment_type' => $paymentType,
                'amount' => $paymentData['amount'],
                'currency' => $currency,
                'is_outgoing' => $paymentData['is_outgoing'] ?? false,
            ];

        if ($owner !== null && $paymentId === null) {
            $paymentMatch['owner_type'] = $owner->getMorphClass();
            $paymentMatch['owner_id'] = (string) $owner->getKey();
        }

        $payment = Payment::query()->forOwner(OwnerContext::CURRENT, false)->where($paymentMatch)->first() ?? new Payment;

        if ($payment->exists && (
            (string) $payment->purchase_id !== $purchaseId
            || $payment->payment_type !== $paymentType
            || $payment->currency !== $currency
            || (int) $payment->amount !== (int) $paymentData['amount']
        )) {
            throw new ChipValidationException('Payment identifier conflicts with an existing payment.');
        }

        $payment->forceFill(
            array_merge($paymentId !== null ? ['id' => $paymentId] : [], [
                'purchase_id' => $purchaseId,
                'payment_type' => $paymentType,
                'is_outgoing' => $paymentData['is_outgoing'] ?? false,
                'amount' => $paymentData['amount'],
                'currency' => $currency,
                'net_amount' => is_numeric($paymentData['net_amount'] ?? null) ? (int) $paymentData['net_amount'] : (int) $paymentData['amount'],
                'fee_amount' => is_numeric($paymentData['fee_amount'] ?? null) ? (int) $paymentData['fee_amount'] : 0,
                'pending_amount' => $paymentData['pending_amount'] ?? 0,
                'pending_unfreeze_on' => $paymentData['pending_unfreeze_on'] ?? null,
                'description' => $paymentData['description'] ?? null,
                'paid_on' => $paymentData['paid_on'] ?? null,
                'remote_paid_on' => $paymentData['remote_paid_on'] ?? null,
                'created_on' => $payload['created_on'] ?? null,
                'updated_on' => $payload['updated_on'] ?? null,
            ])
        );
        if ($owner !== null && ! $payment->hasOwner()) {
            $payment->assignOwner($owner);
        }

        $payment->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTotalMinor(array $payload, Purchase $purchase): int
    {
        foreach ([
            $payload['purchase']['total'] ?? null,
            $payload['purchase']['total_override'] ?? null,
            $payload['payment']['amount'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return (int) ($purchase->getAttribute('total_minor') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePaymentMethod(array $payload, Purchase $purchase): ?string
    {
        foreach ([
            $payload['payment_method'] ?? null,
            data_get($payload, 'transaction_data.payment_method'),
            data_get($payload, 'payment.payment_method'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        $current = $purchase->getAttribute('payment_method');

        return is_string($current) && $current !== '' ? $current : null;
    }

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return null;
        }

        $owner = OwnerContext::resolve();
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return $owner;
    }
}
