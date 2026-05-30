<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
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
                'affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        try {
            $affiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                (string) $affiliateId,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
                message: 'The selected affiliate is not accessible in the current owner scope.',
            );
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'affiliate_id' => 'The selected affiliate is not accessible in the current owner scope.',
            ]);
        }

        if (! $affiliate instanceof Affiliate) {
            throw ValidationException::withMessages([
                'affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        $notes = isset($data['notes']) && is_string($data['notes']) ? mb_trim($data['notes']) : null;

        return [
            'reference' => 'PAY-' . Str::upper(Str::random(10)),
            'status' => PendingPayout::class,
            'total_minor' => (int) ($data['total_minor'] ?? 0),
            'conversion_count' => 0,
            'currency' => mb_strtoupper((string) ($data['currency'] ?? 'USD')),
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'metadata' => $notes === null || $notes === '' ? null : ['notes' => $notes],
        ];
    }
}
