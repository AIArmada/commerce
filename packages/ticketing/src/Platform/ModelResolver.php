<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Platform;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ModelResolver
{
    /**
     * @return class-string<Model>
     */
    public static function ticketTypeClass(): string
    {
        return self::resolve('ticket_type');
    }

    /**
     * @return class-string<Model>
     */
    public static function passClass(): string
    {
        return self::resolve('pass');
    }

    /**
     * @return class-string<Model>
     */
    public static function passHolderClass(): string
    {
        return self::resolve('pass_holder');
    }

    /**
     * @return class-string<Model>
     */
    public static function resolve(string $key): string
    {
        $model = config('ticketing.models.' . $key);

        if (! is_string($model) || $model === '' || ! is_a($model, Model::class, true)) {
            throw new RuntimeException(sprintf(
                'The ticketing model [%s] is not configured. Set ticketing.models.%s to an Eloquent model class.',
                $key,
                $key,
            ));
        }

        return $model;
    }
}
