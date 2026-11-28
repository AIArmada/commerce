<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Payments\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Contracts\DocumentPaymentRecorder;
use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Features\Payments\Models\DocPayment;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use LogicException;
use Throwable;

final readonly class RecordDocPayment implements DocumentPaymentRecorder
{
    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function record(string $docId, array $paymentData): string
    {
        return (new Doc)->getConnection()->transaction(function () use ($docId, $paymentData): string {
            if (config('docs.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(
                    Doc::class,
                    $docId,
                    message: 'Document is not available in the current owner scope.',
                );
            }

            $lockedDoc = Doc::query()
                ->forOwner()
                ->whereKey($docId)
                ->lockForUpdate()
                ->first();

            if (! $lockedDoc instanceof Doc) {
                throw (new ModelNotFoundException)->setModel(Doc::class, [$docId]);
            }

            return OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($lockedDoc->owner_type, $lockedDoc->owner_id), function () use ($lockedDoc, $paymentData): string {
                $currency = $paymentData['currency'] ?? $lockedDoc->currency;

                if (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/D', $currency) !== 1) {
                    throw new InvalidArgumentException('Payment currency must be a 3-letter ISO code.');
                }

                $paymentCurrency = mb_strtoupper($currency);

                if ($paymentCurrency !== mb_strtoupper($lockedDoc->currency)) {
                    throw new InvalidArgumentException('Payment currency must match the document currency.');
                }

                if (! isset($paymentData['amount_minor']) || ! is_int($paymentData['amount_minor']) || $paymentData['amount_minor'] <= 0) {
                    throw new InvalidArgumentException('Payment amount_minor must be a positive integer.');
                }

                $paymentMethod = $paymentData['payment_method'] ?? null;

                if (! is_string($paymentMethod) || ! array_key_exists($paymentMethod, config('docs.payment_methods', []))) {
                    throw new InvalidArgumentException('Payment payment_method is not supported.');
                }

                $details = $this->validateDetails($paymentData);

                $this->assertPaymentOwners($lockedDoc);

                $transactionId = $paymentData['transaction_id'] ?? null;

                if ($transactionId !== null) {
                    if (! is_string($transactionId) || mb_trim($transactionId) === '' || mb_strlen($transactionId) > 255) {
                        throw new InvalidArgumentException('Payment transaction_id must be a non-empty string of at most 255 characters.');
                    }

                    $existing = $lockedDoc->payments()->where('transaction_id', $transactionId)->first();

                    if ($existing instanceof DocPayment) {
                        if ($existing->amount_minor !== $paymentData['amount_minor'] || $existing->currency !== $paymentCurrency || $existing->payment_method !== $paymentMethod || $existing->status !== DocPaymentStatus::Paid) {
                            throw new InvalidArgumentException('Payment transaction_id already exists with different payment details.');
                        }

                        return (string) $existing->getKey();
                    }
                }

                if (! $lockedDoc->canBePaid()) {
                    throw new InvalidArgumentException('Document is not payable in its current status.');
                }

                $totalPaidBefore = (int) $lockedDoc->payments()
                    ->where('status', DocPaymentStatus::Paid->value)
                    ->sum('amount_minor');
                $remainingMinor = $lockedDoc->total_minor - $totalPaidBefore;

                if ($paymentData['amount_minor'] > $remainingMinor) {
                    throw new InvalidArgumentException('Payment amount_minor cannot exceed the outstanding document balance.');
                }

                $paymentAttributes = [
                    'amount_minor' => $paymentData['amount_minor'],
                    'currency' => $paymentCurrency,
                    'payment_method' => $paymentMethod,
                    'status' => DocPaymentStatus::Paid,
                    'paid_at' => $details['paid_at'] ?? CarbonImmutable::now(),
                ];

                $paymentAttributes = array_merge($paymentAttributes, $details);

                $payment = $lockedDoc->payments()->make($paymentAttributes);
                $payment->save();

                $totalPaid = $totalPaidBefore + $payment->amount_minor;

                if ($totalPaid === $lockedDoc->total_minor) {
                    app(DocumentLifecycle::class)->changeStatus((string) $lockedDoc->getKey(), Paid::class, "Payment recorded: {$payment->amount_minor} {$payment->currency} minor units");
                } elseif ($totalPaid > 0) {
                    app(DocumentLifecycle::class)->changeStatus(
                        (string) $lockedDoc->getKey(),
                        PartiallyPaid::class,
                        "Partial payment recorded: {$payment->amount_minor} {$payment->currency} minor units",
                    );
                }

                return (string) $payment->getKey();
            });
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validateDetails(array $data): array
    {
        $details = [];

        foreach (['reference' => 255, 'notes' => 500, 'transaction_id' => 255] as $field => $maximum) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value !== null && (! is_string($value) || mb_strlen($value) > $maximum)) {
                throw new InvalidArgumentException("Payment field `{$field}` must be a string of at most {$maximum} characters.");
            }

            $details[$field] = $value;
        }

        if (array_key_exists('metadata', $data)) {
            if ($data['metadata'] !== null && ! is_array($data['metadata'])) {
                throw new InvalidArgumentException('Payment field `metadata` must be an array.');
            }

            $details['metadata'] = $data['metadata'];
        }

        if (isset($data['paid_at'])) {
            $details['paid_at'] = self::resolvePaidAt($data['paid_at']);
        }

        return $details;
    }

    private function assertPaymentOwners(Doc $doc): void
    {
        $mismatched = $doc->payments()->withoutOwnerScope()
            ->where(function (Builder $query) use ($doc): void {
                foreach (['owner_type' => $doc->owner_type, 'owner_id' => $doc->owner_id] as $column => $value) {
                    if ($value === null) {
                        $query->orWhereNotNull($column);
                    } else {
                        $query->orWhereNull($column)->orWhere($column, '<>', $value);
                    }
                }
            })->exists();

        if ($mismatched) {
            throw new LogicException('Document payment ownership is inconsistent.');
        }
    }

    private static function resolvePaidAt(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && mb_trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
            }
        }

        throw new InvalidArgumentException('Payment paid_at must be a valid date.');
    }
}
