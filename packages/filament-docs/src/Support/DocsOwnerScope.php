<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DocsOwnerScope
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query): Builder
    {
        if (! (bool) config('docs.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        $model = $query->getModel();

        if (! method_exists($model, 'scopeForOwner')) {
            return $query;
        }

        /** @phpstan-ignore-next-line dynamic scope */
        return $query->forOwner($owner, $includeGlobal);
    }

    /**
     * @return Builder<Doc>
     */
    public static function applyToDocs(Builder $query): Builder
    {
        /** @var Builder<Doc> $query */
        return self::apply($query);
    }

    public static function assertCanAccessDoc(Doc $doc): void
    {
        if (! (bool) config('docs.owner.enabled', false)) {
            return;
        }

        $owner = self::resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        $isAllowed = match (true) {
            $owner !== null => $doc->belongsToOwner($owner) || ($includeGlobal && $doc->isGlobal()),
            default => $includeGlobal && $doc->isGlobal(),
        };

        if (! $isAllowed) {
            throw new NotFoundHttpException('Document not found.');
        }
    }

    private static function resolveOwner(): ?Model
    {
        if (! (bool) config('docs.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
