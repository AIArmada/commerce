<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardPayoutRequest>
 */
class RewardPayoutRequestFactory extends Factory
{
    protected $model = RewardPayoutRequest::class;

    public function definition(): array
    {
        $awardId = Str::uuid();

        return [
            'award_id' => $awardId,
            'payee_id' => Str::uuid(),
            'program_id' => Str::uuid(),
            'currency' => 'MYR',
            'amount_minor' => 1000,
            'fee_minor' => 0,
            'fee_payer' => 'program',
            'processor' => 'manual',
            'operation_key' => "reward-payout:{$awardId}",
            'first_reference' => 'RWP-' . mb_strtoupper(Str::random(12)),
            'external_payout_id' => null,
            'external_reference_id' => null,
            'external_provider' => null,
            'status' => RewardPayoutRequestStatus::Requested,
            'provider_reference' => null,
            'failure_code' => null,
            'payee_snapshot' => ['method' => 'manual', 'amount_minor' => 1000, 'currency' => 'MYR'],
            'evidence' => null,
            'requested_by_type' => 'user',
            'requested_by_id' => Str::uuid(),
            'requested_by_role' => 'staff',
            'approved_by' => null,
            'confirmed_by' => null,
            'requested_at' => CarbonImmutable::now('UTC'),
            'approved_at' => null,
            'submitted_at' => null,
            'confirmed_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
        ];
    }
}
