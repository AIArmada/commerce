<?php

declare(strict_types=1);

namespace AIArmada\Events\Models\Concerns;

use Illuminate\Support\Str;

trait UsesEventUuid
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected static function bootUsesEventUuid(): void
    {
        static::creating(function ($model) {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
