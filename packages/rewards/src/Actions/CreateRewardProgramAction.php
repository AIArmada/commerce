<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Models\RewardProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff-driven program creation (server/domain authorized; no member endpoint
 * in this slice). Programs are born as disabled drafts; activation is a
 * separate explicit transition.
 *
 * Ownership is a morph tuple: null/null creates a global (platform) program
 * visible to every owner scope, while an explicit tuple scopes the program
 * to that owner. Persisted owner tuples are immutable after creation.
 */
final readonly class CreateRewardProgramAction
{
    use AsAction;

    public function handle(
        string $familyKey,
        string $title,
        RewardProgramMode | string $mode,
        Model | string | null $ownerType,
        ?string $ownerId,
        string $purpose,
    ): RewardProgram {
        $familyKey = mb_trim($familyKey);
        $title = mb_trim($title);
        $purpose = mb_trim($purpose);

        if (preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $familyKey) !== 1) {
            throw new InvalidArgumentException('Family key must be a 1..64 slug.');
        }

        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Title must be a non-empty string up to 191 characters.');
        }

        if ($purpose === '' || mb_strlen($purpose) > 191) {
            throw new InvalidArgumentException('Purpose must be an explicit non-empty scope up to 191 characters.');
        }

        $modeEnum = $mode instanceof RewardProgramMode ? $mode : RewardProgramMode::tryFrom($mode);

        if (! $modeEnum instanceof RewardProgramMode) {
            throw new InvalidArgumentException('Unknown program mode.');
        }

        [$resolvedType, $resolvedId] = self::resolveOwner($ownerType, $ownerId);

        try {
            return RewardProgram::query()->create([
                'family_key' => $familyKey,
                'title' => $title,
                'mode' => $modeEnum,
                'owner_type' => $resolvedType,
                'owner_id' => $resolvedId,
                'purpose' => $purpose,
                'status' => RewardProgramStatus::Draft,
            ]);
        } catch (QueryException $exception) {
            if (self::isUniqueViolation($exception)) {
                throw new InvalidArgumentException('Program family already exists.', 0, $exception);
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function resolveOwner(Model | string | null $ownerType, ?string $ownerId): array
    {
        if ($ownerType instanceof Model) {
            $ownerId = $ownerId ?? (string) $ownerType->getKey();
            $ownerType = $ownerType->getMorphClass();
        }

        if ($ownerType === null && $ownerId === null) {
            return [null, null];
        }

        if (! is_string($ownerType) || $ownerType === '' || mb_strlen($ownerType) > 191) {
            throw new InvalidArgumentException('Owner type must be a morph alias/class of 1..191 characters, or null for a global program.');
        }

        if ($ownerId === null || ! Str::isUuid($ownerId)) {
            throw new InvalidArgumentException('Owned programs require an explicit owner UUID.');
        }

        $class = Relation::getMorphedModel($ownerType) ?? $ownerType;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException("Owner type [{$ownerType}] is not a resolvable model morph.");
        }

        return [$ownerType, $ownerId];
    }

    public static function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if (in_array($code, ['23000', '23505'], true)) {
            return true;
        }

        return mb_stripos($exception->getMessage(), 'unique') !== false
            || mb_stripos($exception->getMessage(), 'duplicate') !== false;
    }
}
