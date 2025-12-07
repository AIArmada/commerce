<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Actions\Affiliates\ApproveAffiliate;
use AIArmada\Affiliates\Actions\Affiliates\CreateAffiliate;
use AIArmada\Affiliates\Actions\Affiliates\GenerateAffiliateCode;
use AIArmada\Affiliates\Actions\Affiliates\RejectAffiliate;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for affiliate registration operations.
 *
 * This service now delegates to individual Actions for cleaner architecture.
 *
 * @deprecated Use the individual Actions directly:
 *             - CreateAffiliate::run($data, $owner)
 *             - ApproveAffiliate::run($affiliate)
 *             - RejectAffiliate::run($affiliate)
 *             - GenerateAffiliateCode::run($name)
 */
final class AffiliateRegistrationService
{
    public function __construct(
        private readonly CreateAffiliate $createAffiliate,
        private readonly ApproveAffiliate $approveAffiliate,
        private readonly RejectAffiliate $rejectAffiliate,
        private readonly GenerateAffiliateCode $generateCode,
    ) {
    }

    /**
     * Register a new affiliate.
     *
     * @param  array<string, mixed>  $data
     *
     * @deprecated Use CreateAffiliate::run($data, $owner) instead
     */
    public function register(array $data, ?Model $owner = null): Affiliate
    {
        return $this->createAffiliate->handle($data, $owner);
    }

    /**
     * Approve a pending affiliate.
     *
     * @deprecated Use ApproveAffiliate::run($affiliate) instead
     */
    public function approve(Affiliate $affiliate): Affiliate
    {
        return $this->approveAffiliate->handle($affiliate);
    }

    /**
     * Reject a pending affiliate.
     *
     * @deprecated Use RejectAffiliate::run($affiliate) instead
     */
    public function reject(Affiliate $affiliate): Affiliate
    {
        return $this->rejectAffiliate->handle($affiliate);
    }

    /**
     * Check if registration is enabled.
     */
    public function isRegistrationEnabled(): bool
    {
        return (bool) config('affiliates.registration.enabled', true);
    }

    /**
     * Get the approval mode.
     */
    public function getApprovalMode(): RegistrationApprovalMode
    {
        $mode = config('affiliates.registration.approval_mode', 'admin');

        return RegistrationApprovalMode::tryFrom($mode) ?? RegistrationApprovalMode::Admin;
    }

    /**
     * Generate a unique affiliate code.
     *
     * @deprecated Use GenerateAffiliateCode::run($name) instead
     */
    public function generateCode(string $name = ''): string
    {
        return $this->generateCode->handle($name);
    }
}
