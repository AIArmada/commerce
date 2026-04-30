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
            return $query->whereRaw('1 = 0');
        }

        if ($owner === null && ! $includeGlobal) {
            return $query->whereRaw('1 = 0');
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
        self::assertCanAccessRecord($doc, 'Document not found.');
    }

    public static function assertCanAccessRecord(Model $record, string $message = 'Record not found.'): void
    {
        self::assertRecordPermission($record, $message, false);
    }

    public static function assertCanMutateDoc(Doc $doc): void
    {
        self::assertCanMutateRecord($doc, 'Document not found.');
    }

    public static function assertCanMutateRecord(Model $record, string $message = 'Record not found.'): void
    {
        self::assertRecordPermission($record, $message, true);
    }

    private static function assertRecordPermission(Model $record, string $message, bool $forMutation): void
    {
        if (! (bool) config('docs.owner.enabled', false)) {
            return;
        }

        $owner = self::resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if (! method_exists($record, 'belongsToOwner') || ! method_exists($record, 'isGlobal')) {
            throw new NotFoundHttpException($message);
        }

        if ($forMutation) {
            $isAllowed = match (true) {
                $owner !== null => $record->belongsToOwner($owner),
                default => OwnerContext::isExplicitGlobal() && $record->isGlobal(),
            };
        } else {
            $isAllowed = match (true) {
                $owner !== null => $record->belongsToOwner($owner) || ($includeGlobal && $record->isGlobal()),
                default => $includeGlobal && $record->isGlobal(),
            };
        }

        if (! $isAllowed) {
            throw new NotFoundHttpException($message);
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
