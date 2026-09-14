<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cashier\Contracts\PaymentContract;
use LogicException;

/**
 * Immutable scalar snapshot of a payment for queued event payloads.
 *
 * Live PaymentContract implementations wrap gateway SDK objects that are
 * expensive or impossible to serialize. Queued listeners receive this
 * snapshot instead and must re-resolve the live payment when they need
 * gateway operations. Metadata is intentionally dropped: full gateway
 * payloads must not sit in queued jobs.
 */
final readonly class SnapshotPayment implements PaymentContract
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(private array $snapshot) {}

    /**
     * Capture scalar state from a live payment.
     *
     * @return array<string, mixed>
     */
    public static function capture(PaymentContract $payment): array
    {
        return [
            'id' => $payment->id(),
            'gateway' => $payment->gateway(),
            'raw_amount' => $payment->rawAmount(),
            'amount' => $payment->amount(),
            'currency' => $payment->currency(),
            'status' => $payment->status(),
            'error_code' => $payment->errorCode(),
            'is_pending' => $payment->isPending(),
            'is_succeeded' => $payment->isSucceeded(),
            'is_failed' => $payment->isFailed(),
            'is_canceled' => $payment->isCanceled(),
            'is_refunded' => $payment->isRefunded(),
            'requires_action' => $payment->requiresAction(),
            'requires_redirect' => $payment->requiresRedirect(),
            'redirect_url' => $payment->redirectUrl(),
            'receipt_url' => $payment->receiptUrl(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self($snapshot);
    }

    public function id(): string
    {
        return (string) ($this->snapshot['id'] ?? '');
    }

    public function gateway(): string
    {
        return (string) ($this->snapshot['gateway'] ?? '');
    }

    public function rawAmount(): int
    {
        return (int) ($this->snapshot['raw_amount'] ?? 0);
    }

    public function amount(): string
    {
        return (string) ($this->snapshot['amount'] ?? '');
    }

    public function currency(): string
    {
        return (string) ($this->snapshot['currency'] ?? '');
    }

    public function status(): string
    {
        return (string) ($this->snapshot['status'] ?? '');
    }

    public function metadata(): array
    {
        return [];
    }

    public function errorCode(): ?string
    {
        $errorCode = $this->snapshot['error_code'] ?? null;

        return is_string($errorCode) ? $errorCode : null;
    }

    public function isPending(): bool
    {
        return (bool) ($this->snapshot['is_pending'] ?? false);
    }

    public function isSucceeded(): bool
    {
        return (bool) ($this->snapshot['is_succeeded'] ?? false);
    }

    public function isFailed(): bool
    {
        return (bool) ($this->snapshot['is_failed'] ?? false);
    }

    public function isCanceled(): bool
    {
        return (bool) ($this->snapshot['is_canceled'] ?? false);
    }

    public function isRefunded(): bool
    {
        return (bool) ($this->snapshot['is_refunded'] ?? false);
    }

    public function requiresAction(): bool
    {
        return (bool) ($this->snapshot['requires_action'] ?? false);
    }

    public function requiresRedirect(): bool
    {
        return (bool) ($this->snapshot['requires_redirect'] ?? false);
    }

    public function redirectUrl(): ?string
    {
        $url = $this->snapshot['redirect_url'] ?? null;

        return is_string($url) ? $url : null;
    }

    public function receiptUrl(): ?string
    {
        $url = $this->snapshot['receipt_url'] ?? null;

        return is_string($url) ? $url : null;
    }

    public function validate(): self
    {
        if ($this->isFailed()) {
            throw new LogicException('Cannot validate a snapshot of a failed payment; re-resolve the live payment first.');
        }

        return $this;
    }

    public function asGatewayPayment(): mixed
    {
        return $this->snapshot;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'status' => $this->status(),
            'currency' => $this->currency(),
            'amount' => $this->amount(),
            'raw_amount' => $this->rawAmount(),
            'is_succeeded' => $this->isSucceeded(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'requires_redirect' => $this->requiresRedirect(),
        ];
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }
}
