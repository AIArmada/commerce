<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ConfiguredEventModel
{
    /**
     * @param  class-string<Model>  $default
     * @return class-string<Model>
     */
    public static function classFor(string $configKey, string $default): string
    {
        $model = config($configKey, $default);

        if (is_string($model) && is_a($model, Model::class, true)) {
            /** @var class-string<Model> $model */
            return $model;
        }

        throw new RuntimeException(sprintf(
            'The events model config [%s] must contain an Eloquent model class.',
            $configKey,
        ));
    }
}
