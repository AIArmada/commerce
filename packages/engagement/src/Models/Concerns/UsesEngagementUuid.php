<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models\Concerns;

use Illuminate\Support\Str;

trait UsesEngagementUuid
{
    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    protected static function bootUsesEngagementUuid(): void
    {
        static::creating(function ($model): void {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
