<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\States\PartiallyPaid;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Records document payments and owns the payment-balance transition.
 */
final class DocPaymentRecorder
{
    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function record(Doc $doc, array $paymentData): DocPayment
    {
        return DB::transaction(function () use ($doc, $paymentData): DocPayment {
            $owner = config('docs.owner.enabled', false) ? OwnerContext::resolve() : null;
            $includeGlobal = (bool) config('docs.owner.include_global', false);

            if (config('docs.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(
                    Doc::class,
                    (string) $doc->getKey(),
                    owner: $owner,
                    includeGlobal: $includeGlobal,
                    message: 'Document is not available in the current owner scope.',
                );
            }

            $lockedDoc = Doc::query()
                ->forOwner($owner, $includeGlobal)
                ->whereKey($doc->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedDoc instanceof Doc) {
                throw (new ModelNotFoundException)->setModel(Doc::class, [$doc->getKey()]);
            }

            return OwnerContext::withOwner($lockedDoc->owner, function () use ($lockedDoc, $paymentData): DocPayment {
                $paymentCurrency = mb_strtoupper((string) ($paymentData['currency'] ?? $lockedDoc->currency));

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
                    'paid_at' => self::resolvePaidAt($paymentData['paid_at'] ?? null),
                ];

                foreach (['reference', 'transaction_id', 'notes'] as $textKey) {
                    if (array_key_exists($textKey, $paymentData)) {
                        if ($paymentData[$textKey] !== null && ! is_string($paymentData[$textKey])) {
                            throw new InvalidArgumentException("Payment field `{$textKey}` must be a string.");
                        }

                        $paymentAttributes[$textKey] = $paymentData[$textKey];
                    }
                }

                if (array_key_exists('metadata', $paymentData)) {
                    if ($paymentData['metadata'] !== null && ! is_array($paymentData['metadata'])) {
                        throw new InvalidArgumentException('Payment field `metadata` must be an array.');
                    }

                    $paymentAttributes['metadata'] = $paymentData['metadata'];
                }

                $payment = $lockedDoc->payments()->make($paymentAttributes);
                $payment->save();

                $totalPaid = $totalPaidBefore + $payment->amount_minor;

                if ($totalPaid === $lockedDoc->total_minor) {
                    $lockedDoc->markAsPaid("Payment recorded: {$payment->amount_minor} {$payment->currency} minor units");
                } elseif ($totalPaid > 0) {
                    $lockedDoc->transitionStatusTo(
                        PartiallyPaid::class,
                        "Partial payment recorded: {$payment->amount_minor} {$payment->currency} minor units",
                    );
                }

                return $payment;
            });
        });
    }

    private static function resolvePaidAt(mixed $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now();
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::parse($value->format(DateTimeInterface::ATOM));
        }

        if (is_string($value)) {
            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                // Fall through to the invalid-argument throw below.
            }
        }

        throw new InvalidArgumentException('Payment paid_at must be a valid date.');
    }
}
