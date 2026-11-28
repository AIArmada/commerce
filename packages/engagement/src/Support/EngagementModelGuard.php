<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EngagementModelGuard
{
    public const STRING_MAX_LENGTH = 255;

    public const TEXT_MAX_LENGTH = 65535;

    public static function requireModel(mixed $value, string $argument): Model
    {
        if (! $value instanceof Model) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must be an Eloquent model.',
                $argument,
            ));
        }

        assert(is_a($value, Model::class));

        return $value;
    }

    public static function assertModel(object $value, string $argument): void
    {
        self::requireModel($value, $argument);
    }

    public static function assertContract(mixed $value, string $contract, string $argument): void
    {
        if (! $value instanceof $contract) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must implement %s.',
                $argument,
                $contract,
            ));
        }

        assert($value instanceof $contract);

        self::assertModel($value, $argument);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    public static function requireContract(mixed $value, string $contract, string $argument): object
    {
        self::assertContract($value, $contract, $argument);

        /** @var T $value */
        return $value;
    }

    /**
     * @return array{type: string, id: string}
     */
    public static function identity(object $value, string $argument): array
    {
        self::assertModel($value, $argument);

        /** @var Model $value */
        $id = $value->getKey();

        if ((! is_int($id) && ! is_string($id)) || (string) $id === '') {
            throw new InvalidArgumentException(sprintf('Engagement %s must have a non-empty model key.', $argument));
        }

        return [
            'type' => $value->getMorphClass(),
            'id' => (string) $id,
        ];
    }

    /**
     * @return array{type: string, id: string}
     */
    public static function assertWritableIdentity(object $value, string $argument): array
    {
        $identity = self::identity($value, $argument);

        if (! $value instanceof Model || ! $value->exists) {
            throw new InvalidArgumentException(sprintf('Engagement %s must be persisted.', $argument));
        }

        if (method_exists($value::class, 'ownerScopeConfig') && $value::class::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner($value::class, $identity['id']);
        } elseif (! $value->newQuery()->whereKey($identity['id'])->exists()) {
            throw new InvalidArgumentException(sprintf('Engagement %s does not exist.', $argument));
        }

        return $identity;
    }

    public static function boundedString(mixed $value, string $field, int $max = self::STRING_MAX_LENGTH): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must be a string.',
                $field,
            ));
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must not exceed %d characters.',
                $field,
                $max,
            ));
        }

        return $value;
    }

    public static function requiredString(mixed $value, string $field, int $max = self::STRING_MAX_LENGTH): string
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must be a non-empty string.',
                $field,
            ));
        }

        return self::boundedString($value, $field, $max) ?? '';
    }

    /**
     * @return array<string|int, mixed>|null
     */
    public static function optionalArray(mixed $value, string $field): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must be an array.',
                $field,
            ));
        }

        if (json_encode($value) === false) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must be JSON serializable.',
                $field,
            ));
        }

        return $value;
    }

    public static function offsetMinutes(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Engagement offset_minutes must be an integer.');
    }
}
