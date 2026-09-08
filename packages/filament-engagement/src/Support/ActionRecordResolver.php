<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ActionRecordResolver
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $record
     * @return TModel
     */
    public static function resolve(Model $record): Model
    {
        $resolved = OwnerUiScope::findForRecordOwner(
            $record::class,
            $record,
            $record->getKey(),
        );

        if (! $resolved instanceof Model || $resolved::class !== $record::class) {
            throw new AuthorizationException('The selected record is not accessible in the current owner scope.');
        }

        return $resolved;
    }

    public static function resolveOrFail(mixed $record): Model
    {
        if (! $record instanceof Model) {
            throw new InvalidArgumentException('Filament engagement actions require an Eloquent record.');
        }

        return self::resolve($record);
    }
}
