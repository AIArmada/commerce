<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralRewardSpecValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class CreateReferralProgram
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): ReferralProgram
    {
        $name = is_string($attributes['name'] ?? null) ? mb_trim($attributes['name']) : '';
        $outcome = is_string($attributes['qualifier_outcome'] ?? null) ? mb_trim($attributes['qualifier_outcome']) : '';

        if ($name === '' || $outcome === '') {
            throw new InvalidArgumentException('Referral programs require a name and qualifier outcome.');
        }

        $currency = mb_strtoupper(mb_trim((string) ($attributes['currency'] ?? config('referrals.defaults.currency', 'MYR'))));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Referral program currency must be an explicit 3-letter code.');
        }

        $statusValue = $attributes['status'] ?? ReferralProgramStatus::Draft;
        $status = $statusValue instanceof ReferralProgramStatus
            ? $statusValue
            : (is_string($statusValue) ? ReferralProgramStatus::tryFrom($statusValue) : null);

        if (! $status instanceof ReferralProgramStatus) {
            throw new InvalidArgumentException('Referral program status is invalid.');
        }

        $expiryDays = $this->nullableNonNegativeInt($attributes['invite_expiry_days'] ?? null, 'invite_expiry_days');

        if ($expiryDays !== null && $expiryDays < 1) {
            throw new InvalidArgumentException('Referral program [invite_expiry_days] must be at least one day or null.');
        }

        $maxInvites = $this->nullableNonNegativeInt($attributes['max_invites_per_referrer'] ?? null, 'max_invites_per_referrer');
        $maxQualified = $this->nullableNonNegativeInt($attributes['max_qualified_per_referrer_per_month'] ?? null, 'max_qualified_per_referrer_per_month');

        $validator = app(ReferralRewardSpecValidator::class);
        $referrerReward = $validator->validate($attributes['referrer_reward'] ?? null, $currency);
        $refereeReward = $validator->validate($attributes['referee_reward'] ?? null, $currency);

        $slug = Str::slug(is_string($attributes['slug'] ?? null) ? $attributes['slug'] : $name);

        if ($slug === '') {
            throw new InvalidArgumentException('Referral programs require a non-empty slug.');
        }

        if ((bool) config('referrals.owner.enabled', true)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        } else {
            $owner = null;
        }

        $program = new ReferralProgram;
        $program->forceFill([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'qualifier_outcome' => $outcome,
            'invite_expiry_days' => $expiryDays,
            'max_invites_per_referrer' => $maxInvites,
            'max_qualified_per_referrer_per_month' => $maxQualified,
            'referrer_reward' => $referrerReward,
            'referee_reward' => $refereeReward,
            'currency' => $currency,
            'starts_at' => $this->date($attributes['starts_at'] ?? null),
            'ends_at' => $this->date($attributes['ends_at'] ?? null),
            'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
        ]);

        if ($owner !== null) {
            $typeColumn = (string) config('referrals.owner.owner_type_column', 'owner_type');
            $idColumn = (string) config('referrals.owner.owner_id_column', 'owner_id');
            $program->setAttribute($typeColumn, $owner->getMorphClass());
            $program->setAttribute($idColumn, $owner->getKey());
        }

        if ($program->starts_at !== null && $program->ends_at !== null && $program->starts_at->greaterThanOrEqualTo($program->ends_at)) {
            throw new InvalidArgumentException('Referral program ends_at must be after starts_at.');
        }

        $program->save();

        return $program;
    }

    private function nullableNonNegativeInt(mixed $value, string $attribute): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException(sprintf('Referral program [%s] must be a non-negative integer or null.', $attribute));
        }

        return $value;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Referral program dates must be strings or CarbonImmutable values.');
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Referral program date is invalid.', previous: $exception);
        }
    }
}
