<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EngagementModelGuard
{
    public static function assertModel(object $value, string $argument): void
    {
        if (! $value instanceof Model) {
            throw new InvalidArgumentException(sprintf(
                'Engagement %s must be an Eloquent model.',
                $argument,
            ));
        }

        assert(is_a($value, Model::class));
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
        return [
            'type' => $value->getMorphClass(),
            'id' => (string) $value->getKey(),
        ];
    }
}
