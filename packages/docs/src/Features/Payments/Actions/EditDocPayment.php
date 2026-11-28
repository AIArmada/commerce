<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Payments\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentPaymentEditor;
use AIArmada\Docs\Models\Doc;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class EditDocPayment implements DocumentPaymentEditor
{
    public function edit(string $docId, string $paymentId, array $data): void
    {
        DB::transaction(function () use ($docId, $paymentId, $data): void {
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('docs.owner.include_global', false);

            if (config('docs.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId, owner: $owner, includeGlobal: $includeGlobal);
            }

            $doc = Doc::query()->forOwner($owner, $includeGlobal)->lockForUpdate()->findOrFail($docId);
            $payment = $doc->payments()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->owner_type !== $doc->owner_type || (string) $payment->owner_id !== (string) $doc->owner_id) {
                throw ValidationException::withMessages(['doc_id' => 'Invalid payment record.']);
            }

            foreach (['amount_minor', 'currency', 'payment_method', 'transaction_id', 'status'] as $field) {
                if (array_key_exists($field, $data) && (! is_scalar($data[$field]) && $data[$field] !== null || (string) $data[$field] !== (string) $payment->getRawOriginal($field))) {
                    throw ValidationException::withMessages([$field => 'This field cannot be changed after recording.']);
                }
            }

            $attributes = array_intersect_key($data, array_flip(['reference', 'notes', 'paid_at']));

            foreach (['reference' => 255, 'notes' => 500] as $field => $maximum) {
                if (isset($attributes[$field]) && (! is_string($attributes[$field]) || mb_strlen($attributes[$field]) > $maximum)) {
                    throw ValidationException::withMessages([$field => "Must be a string of at most {$maximum} characters."]);
                }
            }

            if (array_key_exists('paid_at', $attributes)) {
                $date = $attributes['paid_at'];
                if (! $date instanceof DateTimeInterface && (! is_string($date) || mb_trim($date) === '')) {
                    throw ValidationException::withMessages(['paid_at' => 'A valid payment date is required.']);
                }

                try {
                    $attributes['paid_at'] = $date instanceof DateTimeInterface
                        ? CarbonImmutable::instance($date)
                        : CarbonImmutable::parse($date);
                } catch (Throwable) {
                    throw ValidationException::withMessages(['paid_at' => 'A valid payment date is required.']);
                }
            }

            $payment->update($attributes);
        });
    }
}
