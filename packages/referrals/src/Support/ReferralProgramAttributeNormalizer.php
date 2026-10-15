<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Exceptions\ReferralProgramAttributeException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single source of truth for referral program attribute rules.
 *
 * Creation and update share this normalizer so an edit can never accept a
 * value creation would have refused (the create/edit drift that let an
 * operator store an invalid currency or an inverted window through the
 * admin edit page). It returns the full set of program attributes to
 * persist; owner assignment and status transitions stay with the callers.
 */
final class ReferralProgramAttributeNormalizer
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalize(array $attributes, bool $forUpdate = false): array
    {
        if ($forUpdate) {
            $this->assertNoImmutableKeys($attributes);
        }

        $name = is_string($attributes['name'] ?? null) ? mb_trim($attributes['name']) : '';

        if ($name === '') {
            throw ReferralProgramAttributeException::for('name', 'Referral programs require a name and qualifier outcome.');
        }

        $outcome = is_string($attributes['qualifier_outcome'] ?? null) ? mb_trim($attributes['qualifier_outcome']) : '';

        if ($outcome === '') {
            throw ReferralProgramAttributeException::for('qualifier_outcome', 'Referral programs require a name and qualifier outcome.');
        }

        $currency = mb_strtoupper(mb_trim((string) ($attributes['currency'] ?? config('referrals.defaults.currency', 'MYR'))));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ReferralProgramAttributeException::for('currency', 'Referral program currency must be an explicit 3-letter code.');
        }

        $expiryDays = $this->nullableNonNegativeInt($attributes['invite_expiry_days'] ?? null, 'invite_expiry_days');

        if ($expiryDays !== null && $expiryDays < 1) {
            throw ReferralProgramAttributeException::for('invite_expiry_days', 'Referral program [invite_expiry_days] must be at least one day or null.');
        }

        $maxInvites = $this->nullableNonNegativeInt($attributes['max_invites_per_referrer'] ?? null, 'max_invites_per_referrer');
        $maxQualified = $this->nullableNonNegativeInt($attributes['max_qualified_per_referrer_per_month'] ?? null, 'max_qualified_per_referrer_per_month');

        $validator = app(ReferralRewardSpecValidator::class);
        $referrerReward = $validator->validate($attributes['referrer_reward'] ?? null, $currency);
        $refereeReward = $validator->validate($attributes['referee_reward'] ?? null, $currency);

        $this->assertPointsFamiliesCarryOneAmount($referrerReward, $refereeReward);

        $slug = Str::slug(is_string($attributes['slug'] ?? null) ? $attributes['slug'] : $name);

        if ($slug === '') {
            throw ReferralProgramAttributeException::for('slug', 'Referral programs require a non-empty slug.');
        }

        $startsAt = $this->date($attributes['starts_at'] ?? null, 'starts_at');
        $endsAt = $this->date($attributes['ends_at'] ?? null, 'ends_at');

        if ($startsAt !== null && $endsAt !== null && $startsAt->greaterThanOrEqualTo($endsAt)) {
            throw ReferralProgramAttributeException::for('ends_at', 'Referral program ends_at must be after starts_at.');
        }

        $normalized = [
            'name' => $name,
            'slug' => $slug,
            'qualifier_outcome' => $outcome,
            'invite_expiry_days' => $expiryDays,
            'max_invites_per_referrer' => $maxInvites,
            'max_qualified_per_referrer_per_month' => $maxQualified,
            'referrer_reward' => $referrerReward,
            'referee_reward' => $refereeReward,
            'currency' => $currency,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
        ];

        // Status moves only through the matrix-checked status actions, so an
        // update payload may never name it. Creation still accepts it.
        if (! $forUpdate) {
            $normalized['status'] = $this->status($attributes['status'] ?? ReferralProgramStatus::Draft);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertNoImmutableKeys(array $attributes): void
    {
        $immutable = [
            'status',
            'owner_type',
            'owner_id',
            (string) config('referrals.owner.owner_type_column', 'owner_type'),
            (string) config('referrals.owner.owner_id_column', 'owner_id'),
        ];

        foreach (array_unique($immutable) as $key) {
            if (array_key_exists($key, $attributes)) {
                throw ReferralProgramAttributeException::for(
                    $key,
                    sprintf('Referral program [%s] is immutable after creation.', $key),
                );
            }
        }
    }

    private function status(mixed $value): ReferralProgramStatus
    {
        $status = $value instanceof ReferralProgramStatus
            ? $value
            : (is_string($value) ? ReferralProgramStatus::tryFrom($value) : null);

        if (! $status instanceof ReferralProgramStatus) {
            throw ReferralProgramAttributeException::for('status', 'Referral program status is invalid.');
        }

        return $status;
    }

    /**
     * One rewards program's points component carries a single points value,
     * so two points promises can only coexist on one referral program when
     * each side binds its own rewards family. Equal amounts keep sharing a
     * family (or none); differing amounts require a family on both sides.
     *
     * @param  array<string, mixed>|null  $referrerReward
     * @param  array<string, mixed>|null  $refereeReward
     */
    private function assertPointsFamiliesCarryOneAmount(?array $referrerReward, ?array $refereeReward): void
    {
        if ($referrerReward === null || $refereeReward === null) {
            return;
        }

        if (($referrerReward['driver'] ?? null) !== 'reward_points'
            || ($refereeReward['driver'] ?? null) !== 'reward_points') {
            return;
        }

        if (($referrerReward['points'] ?? null) === ($refereeReward['points'] ?? null)) {
            return;
        }

        $referrerFamily = $referrerReward['family_key'] ?? null;
        $refereeFamily = $refereeReward['family_key'] ?? null;

        if ($referrerFamily === null || $refereeFamily === null) {
            throw ReferralProgramAttributeException::for('referrer_reward', 'Points rewards with different amounts require an explicit family_key on both sides so each side binds its own rewards program.');
        }

        if ($referrerFamily === $refereeFamily) {
            throw ReferralProgramAttributeException::for('referrer_reward', 'Points rewards sharing a family_key must promise the same points amount; give each side a distinct family_key.');
        }
    }

    private function nullableNonNegativeInt(mixed $value, string $attribute): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw ReferralProgramAttributeException::for(
                $attribute,
                sprintf('Referral program [%s] must be a non-negative integer or null.', $attribute),
            );
        }

        return $value;
    }

    private function date(mixed $value, string $attribute): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if (! is_string($value)) {
            throw ReferralProgramAttributeException::for($attribute, 'Referral program dates must be strings or CarbonImmutable values.');
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable $exception) {
            throw ReferralProgramAttributeException::for($attribute, 'Referral program date is invalid.', $exception);
        }
    }
}
