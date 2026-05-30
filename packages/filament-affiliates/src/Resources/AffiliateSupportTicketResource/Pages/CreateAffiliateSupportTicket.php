<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class CreateAffiliateSupportTicket extends CreateRecord
{
    protected static string $resource = AffiliateSupportTicketResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $affiliateId = $data['affiliate_id'] ?? null;

        if (! is_string($affiliateId) && ! is_int($affiliateId)) {
            throw ValidationException::withMessages([
                'affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        $data['affiliate_id'] = $this->resolveOwnedAffiliateId((string) $affiliateId);

        return $data;
    }

    private function resolveOwnedAffiliateId(string $affiliateId): string
    {
        try {
            return (string) OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $affiliateId,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
                message: 'The selected affiliate is not accessible in the current owner scope.',
            )->getKey();
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'affiliate_id' => 'The selected affiliate is not accessible in the current owner scope.',
            ]);
        }
    }
}
