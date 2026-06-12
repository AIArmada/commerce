<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models\Concerns;

use Illuminate\Support\Str;

trait UsesEngagementUuid
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected static function bootUsesEngagementUuid(): void
    {
        static::creating(function ($model) {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
