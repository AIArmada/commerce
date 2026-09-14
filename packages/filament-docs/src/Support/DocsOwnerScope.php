<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Unique;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DocsOwnerScope
{
    public static function assertCanAccessRecord(Model $record, string $message): void
    {
        if (! OwnerUiScope::canAccessRecord($record)) {
            throw new NotFoundHttpException($message);
        }
    }

    public static function assertCanMutateRecord(Model $record, string $message): void
    {
        if (! OwnerUiScope::canMutateRecord($record)) {
            throw new NotFoundHttpException($message);
        }
    }

    public static function scopeUniqueRuleToOwner(Unique $rule): Unique
    {
        if (! (bool) config('docs.owner.enabled', false)) {
            return $rule;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if ($owner instanceof Model) {
            if ($includeGlobal) {
                return $rule->where(function (Builder $query) use ($owner): void {
                    $query
                        ->where(function (Builder $ownerQuery) use ($owner): void {
                            $ownerQuery
                                ->where('owner_type', $owner->getMorphClass())
                                ->where('owner_id', (string) $owner->getKey());
                        })
                        ->orWhere(function (Builder $globalQuery): void {
                            $globalQuery->whereNull('owner_type')->whereNull('owner_id');
                        });
                });
            }

            return $rule
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey());
        }

        return $rule
            ->whereNull('owner_type')
            ->whereNull('owner_id');
    }
}
