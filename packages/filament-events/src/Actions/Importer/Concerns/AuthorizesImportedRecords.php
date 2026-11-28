<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer\Concerns;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Enforces registered Laravel policies for importer create and update rows.
 */
trait AuthorizesImportedRecords
{
    protected function authorizeRecordCreate(): void
    {
        $modelClass = static::getModel();

        $user = $this->resolveImportUser();

        if ($user === null) {
            throw new RowImportFailedException('You are not authorized to create this record.');
        }

        if (Gate::getPolicyFor($modelClass) === null) {
            return;
        }

        if (! Gate::forUser($user)->allows('create', $modelClass)) {
            throw new RowImportFailedException('You are not authorized to create this record.');
        }
    }

    protected function authorizeRecordUpdate(): void
    {
        $record = $this->record;

        if (! $record instanceof Model || ! $record->exists) {
            throw new RowImportFailedException('Unable to resolve existing record for update authorization.');
        }

        $persisted = $record->newQuery()->whereKey($record->getKey())->first();

        if (! $persisted instanceof Model) {
            throw new RowImportFailedException('Unable to resolve existing record for update authorization.');
        }

        $user = $this->resolveImportUser();

        if ($user === null) {
            throw new RowImportFailedException('You are not authorized to update this record.');
        }

        if (Gate::getPolicyFor($persisted) === null) {
            return;
        }

        if (! Gate::forUser($user)->allows('update', $persisted)) {
            throw new RowImportFailedException('You are not authorized to update this record.');
        }
    }

    protected function resolveImportUser(): ?Authenticatable
    {
        if ($this->import->getAttribute('user_id') === null) {
            return null;
        }

        $user = $this->import->user;

        return $user instanceof Authenticatable ? $user : null;
    }
}
