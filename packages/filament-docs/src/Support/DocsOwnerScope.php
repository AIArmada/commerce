<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
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
        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', true);

        $model = $query->getModel();

        if (! method_exists($model, 'scopeForOwner')) {
            return $query;
        }

        /** @var Builder<TModel> $scoped */
        $scoped = $model->scopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
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
        if (! config('docs.owner.enabled', false)) {
            return;
        }

        $owner = self::resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', true);

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
        return app(OwnerResolverInterface::class)->resolve();
    }
}
