<?php

declare(strict_types=1);

namespace AIArmada\Seating\Platform;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ModelResolver
{
    /**
     * @return class-string<Model>
     */
    public static function seatMapClass(): string
    {
        return self::resolve('seat_map');
    }

    /**
     * @return class-string<Model>
     */
    public static function seatSectionClass(): string
    {
        return self::resolve('seat_section');
    }

    /**
     * @return class-string<Model>
     */
    public static function seatClass(): string
    {
        return self::resolve('seat');
    }

    /**
     * @return class-string<Model>
     */
    public static function seatHoldClass(): string
    {
        return self::resolve('seat_hold');
    }

    /**
     * @return class-string<Model>
     */
    public static function seatAllocationClass(): string
    {
        return self::resolve('seat_allocation');
    }

    /**
     * @return class-string<Model>
     */
    public static function resolve(string $key): string
    {
        $model = config('seating.models.' . $key);

        if (! is_string($model) || $model === '' || ! is_a($model, Model::class, true)) {
            throw new RuntimeException(sprintf(
                'The seating model [%s] is not configured. Set seating.models.%s to an Eloquent model class.',
                $key,
                $key,
            ));
        }

        return $model;
    }
}
