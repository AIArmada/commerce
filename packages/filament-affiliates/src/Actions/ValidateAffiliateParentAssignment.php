<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class ValidateAffiliateParentAssignment
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data, ?Affiliate $record = null): array
    {
        $parentAffiliateId = $data['parent_affiliate_id'] ?? null;

        if ($parentAffiliateId === null || $parentAffiliateId === '') {
            $data['parent_affiliate_id'] = null;

            return $data;
        }

        if (! is_string($parentAffiliateId) && ! is_int($parentAffiliateId)) {
            throw $this->validationException('Selected parent affiliate is invalid.');
        }

        $parentAffiliateId = (string) $parentAffiliateId;

        if ($record instanceof Affiliate && $parentAffiliateId === (string) $record->getKey()) {
            throw $this->validationException('An affiliate cannot be its own parent.');
        }

        $parentAffiliate = $this->resolveParentAffiliate($parentAffiliateId);

        $data['parent_affiliate_id'] = (string) $parentAffiliate->getKey();

        return $data;
    }

    private function resolveParentAffiliate(string $parentAffiliateId): Affiliate
    {
        if (! Affiliate::ownerScopeConfig()->enabled) {
            $parentAffiliate = Affiliate::query()->find($parentAffiliateId);

            if ($parentAffiliate instanceof Affiliate) {
                return $parentAffiliate;
            }

            throw $this->validationException('Selected parent affiliate could not be found.');
        }

        try {
            /** @var Affiliate $parentAffiliate */
            $parentAffiliate = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $parentAffiliateId,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
                message: 'Selected parent affiliate is not accessible in the current owner scope.',
            );

            return $parentAffiliate;
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw $this->validationException('Selected parent affiliate is not accessible in the current owner scope.');
        }
    }

    private function validationException(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'parent_affiliate_id' => $message,
        ]);
    }
}