<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class CreateAffiliatePayout extends CreateRecord
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $affiliateId = $data['affiliate_id'] ?? null;

        if (! is_string($affiliateId) && ! is_int($affiliateId)) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        if (! (bool) config('affiliates.owner.enabled', false)) {
            $affiliate = Affiliate::query()->find((string) $affiliateId);
        } else {
            try {
                $affiliate = OwnerWriteGuard::findOrFailForOwner(
                    Affiliate::class,
                    (string) $affiliateId,
                    includeGlobal: (bool) config('affiliates.owner.include_global', false),
                    message: 'The selected affiliate is not accessible in the current owner scope.',
                );
            } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
                throw ValidationException::withMessages([
                    'data.affiliate_id' => 'The selected affiliate is not accessible in the current owner scope.',
                ]);
            }
        }

        if (! $affiliate instanceof Affiliate) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        $notes = isset($data['notes']) && is_string($data['notes']) ? mb_trim($data['notes']) : null;
        $reason = isset($data['payout_override_reason']) && is_string($data['payout_override_reason'])
            ? mb_trim($data['payout_override_reason'])
            : '';

        $metadata = $notes === null || $notes === '' ? [] : ['notes' => $notes];

        // Mirror CreatePayout::resolvePayoutOverride: stamp only for
        // disabled affiliates with a reason, throw for everything else
        // non-payable (catch-all, so future statuses fail closed too) —
        // including non-payable affiliates with NO reason, so the form
        // cannot create payouts the completion gate will refuse.
        if (! $affiliate->canReceivePayout()) {
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'data.affiliate_id' => sprintf(
                        'Affiliate "%s" cannot receive payouts (status: %s). Pending affiliates must be approved first; paused affiliates must be unpaused; disabled affiliates require an override reason to release earned balances.',
                        (string) $affiliate->code,
                        $affiliate->status->getValue(),
                    ),
                ]);
            }

            if (! $affiliate->status instanceof Disabled) {
                $message = match (true) {
                    $affiliate->status instanceof Pending => 'Pending affiliates must be approved first; an override reason cannot release their payouts.',
                    $affiliate->status instanceof Paused => 'Paused affiliates must be unpaused first; an override reason cannot release their payouts.',
                    default => sprintf(
                        'Affiliates with status "%s" cannot receive payouts; only disabled affiliates accept an audited override reason.',
                        $affiliate->status->getValue()
                    ),
                };

                throw ValidationException::withMessages([
                    'data.payout_override_reason' => $message,
                ]);
            }

            $metadata['payout_override'] = [
                'reason' => $reason,
                'affiliate_status' => $affiliate->status->getValue(),
                'overridden_at' => CarbonImmutable::now()->toIso8601String(),
                'overridden_by' => auth()->user()?->getAuthIdentifier(),
            ];
        }

        return [
            'reference' => 'PAY-' . Str::upper(Str::random(10)),
            'status' => PendingPayout::class,
            'total_minor' => (int) ($data['total_minor'] ?? 0),
            'conversion_count' => 0,
            'currency' => mb_strtoupper((string) ($data['currency'] ?? 'MYR')),
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'metadata' => $metadata === [] ? null : $metadata,
        ];
    }
}
