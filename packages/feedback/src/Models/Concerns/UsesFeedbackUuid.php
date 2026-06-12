<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models\Concerns;

use Illuminate\Support\Str;

trait UsesFeedbackUuid
{
    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    protected static function bootUsesFeedbackUuid(): void
    {
        static::creating(function ($model): void {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
