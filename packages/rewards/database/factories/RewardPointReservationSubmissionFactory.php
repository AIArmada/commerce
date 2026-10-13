<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Database\Factories;

use AIArmada\Rewards\Models\RewardPointReservationSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardPointReservationSubmission>
 */
class RewardPointReservationSubmissionFactory extends Factory
{
    protected $model = RewardPointReservationSubmission::class;

    public function definition(): array
    {
        return [
            'reservation_key' => 'res-' . Str::lower(Str::random(12)),
            'status' => RewardPointReservationSubmission::STATUS_SENDING,
            'reference' => null,
            'submitted_at' => now('UTC'),
            'resolved_at' => null,
        ];
    }
}
