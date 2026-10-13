<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Database\Factories;

use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Models\ReferralProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReferralProgram> */
final class ReferralProgramFactory extends Factory
{
    protected $model = ReferralProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => ReferralProgramStatus::Draft,
            'qualifier_outcome' => 'first_registration',
            'currency' => 'MYR',
            'referrer_reward' => null,
            'referee_reward' => null,
        ];
    }
}
