<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new affiliate.
 */
final class CreateAffiliate
{
    use AsAction;

    public function __construct(
        private readonly GenerateAffiliateCode $generateCode,
    ) {}

    /**
     * Create a new affiliate with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Model $owner = null): Affiliate
    {
        return DB::transaction(function () use ($data, $owner): Affiliate {
            $approvalMode = $this->getApprovalMode();
            $status = $this->determineStatus($data, $approvalMode);

            $affiliate = new Affiliate([
                'code' => $data['code'] ?? $this->generateCode->handle($data['name'] ?? ''),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'commission_type' => $data['commission_type'] ?? $this->getDefaultCommissionType(),
                'commission_rate' => $data['commission_rate'] ?? $this->getDefaultCommissionRate(),
                'currency' => $data['currency'] ?? config('affiliates.currency.default', 'USD'),
                'contact_email' => $data['contact_email'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($owner) {
                $affiliate->owner_type = $owner->getMorphClass();
                $affiliate->owner_id = $owner->getKey();
            }

            if ($status === AffiliateStatus::Active) {
                $affiliate->activated_at = now();
            }

            $affiliate->save();

            return $affiliate;
        });
    }

    private function getApprovalMode(): RegistrationApprovalMode
    {
        $mode = config('affiliates.registration.approval_mode', 'admin');

        return RegistrationApprovalMode::tryFrom($mode) ?? RegistrationApprovalMode::Admin;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function determineStatus(array $data, RegistrationApprovalMode $approvalMode): AffiliateStatus
    {
        if (isset($data['status'])) {
            return $data['status'] instanceof AffiliateStatus
                ? $data['status']
                : (AffiliateStatus::tryFrom($data['status']) ?? $approvalMode->defaultStatus());
        }

        return $approvalMode->defaultStatus();
    }

    private function getDefaultCommissionType(): CommissionType
    {
        $type = config('affiliates.registration.default_commission_type', 'percentage');

        return CommissionType::tryFrom($type) ?? CommissionType::Percentage;
    }

    private function getDefaultCommissionRate(): int
    {
        return (int) config('affiliates.registration.default_commission_rate', 1000);
    }
}
