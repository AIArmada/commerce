<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralProgramAttributeNormalizer;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Updates the mutable attributes of a referral program.
 *
 * The record is re-resolved through ReferralProgramGuard with a row lock
 * inside one transaction, so a concurrent status change or owner mismatch
 * cannot be raced. Status and owner tuples are immutable here: lifecycle
 * moves only through the matrix-checked status actions, and a program's
 * owner is fixed at creation.
 *
 * Attributes absent from the payload keep their stored values, so a partial
 * edit never re-derives the slug or silently resets the currency. The shared
 * normalizer applies exactly the creation rules, and already-frozen reward
 * rows are untouched — a qualified invite keeps the promise it was made.
 */
final class UpdateReferralProgram
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(ReferralProgram | string $program, array $attributes): ReferralProgram
    {
        $normalizer = app(ReferralProgramAttributeNormalizer::class);

        return DB::transaction(function () use ($program, $attributes, $normalizer): ReferralProgram {
            $resolved = app(ReferralProgramGuard::class)->program($program, lock: true);

            $effective = array_replace($this->currentAttributes($resolved), $attributes);

            // A name-only edit re-derives the slug exactly as creation would
            // (so a name that slugifies to nothing is refused). Any other
            // partial edit keeps the stored slug, so an unrelated change never
            // rewrites the program's public identifier.
            if (array_key_exists('name', $attributes) && ! array_key_exists('slug', $attributes)) {
                unset($effective['slug']);
            }

            $resolved->forceFill($normalizer->normalize($effective, forUpdate: true));
            $resolved->save();

            return $resolved;
        });
    }

    /**
     * The stored values an update merges over, read through the model's casts
     * so the normalizer sees the same shapes creation does (arrays for the
     * reward specs, CarbonImmutable for the dates).
     *
     * @return array<string, mixed>
     */
    private function currentAttributes(ReferralProgram $program): array
    {
        return [
            'name' => $program->name,
            'slug' => $program->slug,
            'qualifier_outcome' => $program->qualifier_outcome,
            'invite_expiry_days' => $program->invite_expiry_days,
            'max_invites_per_referrer' => $program->max_invites_per_referrer,
            'max_qualified_per_referrer_per_month' => $program->max_qualified_per_referrer_per_month,
            'referrer_reward' => $program->referrer_reward,
            'referee_reward' => $program->referee_reward,
            'currency' => $program->currency,
            'starts_at' => $program->starts_at,
            'ends_at' => $program->ends_at,
            'metadata' => $program->metadata,
        ];
    }
}
