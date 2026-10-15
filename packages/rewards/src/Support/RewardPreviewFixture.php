<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardVerificationLevel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Supplied trusted-fact fixture for deterministic qualification preview.
 *
 * This is FIXTURE evidence only: it is explicitly supplied by the caller for
 * policy computation and is never actual source integration. Preview results
 * always carry that label and must not be presented as proof of real source
 * facts.
 */
final readonly class RewardPreviewFixture
{
    public const string FIXTURE_LABEL = 'FIXTURE-NOT-SOURCE';

    /**
     * Synthetic fixture evidence only (never source proof). Validity flags
     * default to a clean source so existing valid fixtures keep working;
     * bot/cancelled/invalid/unattributed fixtures are explicitly rejected by
     * the engine, never treated as eligible. The beneficiary role defaults
     * to the fail-closed sharer-bound Recipient: an unattributed fixture
     * stays refused unless the caller names an actor-bound role.
     *
     * @param  list<string>  $claimedFamilyKeys  Families already claiming this source/component (frozen snapshots).
     */
    public function __construct(
        public string $sourceBusinessKey,
        public string $outcome,
        public ?string $category,
        public CarbonImmutable $occurredAt,
        public RewardVerificationLevel $verification,
        public bool $isFirstTime,
        public ?string $actorId,
        public ?string $sharerId,
        public bool $isClaimedIdentity,
        public bool $isAttributed,
        public bool $isAmbiguousDevice = false,
        public array $claimedFamilyKeys = [],
        public bool $isBot = false,
        public bool $isCancelled = false,
        public bool $isValidSource = true,
        public RewardBeneficiaryRole $beneficiaryRole = RewardBeneficiaryRole::Recipient,
    ) {
        if ($this->sourceBusinessKey === '' || mb_strlen($this->sourceBusinessKey) > 191) {
            throw new InvalidArgumentException('Fixture source_business_key must be 1..191 characters.');
        }

        if ($this->category !== null && preg_match('/^[a-z0-9][a-z0-9_\-]{0,31}$/', $this->category) !== 1) {
            throw new InvalidArgumentException('Fixture category must be a 1..32 slug or null.');
        }

        foreach (['actorId' => $this->actorId, 'sharerId' => $this->sharerId] as $field => $value) {
            if ($value !== null && ($value === '' || mb_strlen($value) > 64)) {
                throw new InvalidArgumentException("Fixture [{$field}] must be null or 1..64 characters.");
            }
        }

        if ($this->claimedFamilyKeys !== [] && ! array_is_list($this->claimedFamilyKeys)) {
            throw new InvalidArgumentException('Fixture claimed_family_keys must be a list.');
        }

        foreach ($this->claimedFamilyKeys as $family) {
            if (! is_string($family) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $family) !== 1) {
                throw new InvalidArgumentException('Fixture claimed_family_keys must be family slugs.');
            }
        }
    }
}
