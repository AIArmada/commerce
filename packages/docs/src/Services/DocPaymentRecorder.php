<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\States\PartiallyPaid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

                $totalPaidBefore = (int) $lockedDoc->payments()->sum('amount_minor');
                $remainingMinor = $lockedDoc->total_minor - $totalPaidBefore;

                if ($paymentData['amount_minor'] > $remainingMinor) {
                    throw new InvalidArgumentException('Payment amount_minor cannot exceed the outstanding document balance.');
                }

                $paymentAttributes = array_diff_key($paymentData, ['doc_id' => true]);
                $payment = $lockedDoc->payments()->make(array_merge($paymentAttributes, [
                    'paid_at' => $paymentData['paid_at'] ?? CarbonImmutable::now(),
                    'currency' => $paymentCurrency,
                ]));
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
}
