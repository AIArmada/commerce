<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Illuminate\Database\Eloquent\Model;
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
}
