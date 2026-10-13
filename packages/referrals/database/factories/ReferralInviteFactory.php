<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Database\Factories;

use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReferralInvite> */
final class ReferralInviteFactory extends Factory
{
    protected $model = ReferralInvite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contact = fake()->unique()->safeEmail();

        return [
            'program_id' => ReferralProgram::factory(),
            'referrer_type' => 'user',
            'referrer_id' => (string) Str::uuid(),
            'invitee_hash' => hash('sha256', $contact),
            'invitee_contact_encrypted' => $contact,
            'token_hash' => hash('sha256', Str::random(32)),
            'expired_at' => now()->addDays(30),
        ];
    }
}
