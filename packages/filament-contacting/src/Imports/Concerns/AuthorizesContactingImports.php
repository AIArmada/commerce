<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Imports\Concerns;

use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Enforces read-only UI guards and registered Laravel policies for contacting import rows.
 */
trait AuthorizesContactingImports
{
    protected function authorizeContactingCreate(bool $readOnly): void
    {
        if ($readOnly) {
            throw new RowImportFailedException('Import is disabled while this contacting resource is read only.');
        }

        $modelClass = static::getModel();

        if (Gate::getPolicyFor($modelClass) === null) {
            return;
        }

        $user = $this->resolveImportUser();

        if (! Gate::forUser($user)->allows('create', $modelClass)) {
            throw new RowImportFailedException('You are not authorized to create this record.');
        }
    }

    protected function authorizeContactingUpdate(bool $readOnly): void
    {
        if ($readOnly) {
            throw new RowImportFailedException('Import is disabled while this contacting resource is read only.');
        }

        $record = $this->record;

        if (! $record instanceof Model || ! $record->exists) {
            throw new RowImportFailedException('Unable to resolve existing record for update authorization.');
        }

        $persisted = $record->newQuery()->whereKey($record->getKey())->first() ?? $record;

        if (Gate::getPolicyFor($persisted) === null) {
            return;
        }

        $user = $this->resolveImportUser();

        if (! Gate::forUser($user)->allows('update', $persisted)) {
            throw new RowImportFailedException('You are not authorized to update this record.');
        }
    }

    protected static function contactingUiGuard(): GuardsContactingUi
    {
        return app(GuardsContactingUi::class);
    }

    private function resolveImportUser(): ?Authenticatable
    {
        if ($this->import->relationLoaded('user')) {
            $loaded = $this->import->getRelation('user');

            return $loaded instanceof Authenticatable ? $loaded : null;
        }

        if ($this->import->getAttribute('user_id') !== null) {
            $resolved = $this->import->user;

            return $resolved instanceof Authenticatable ? $resolved : null;
        }

        $current = auth()->user();

        return $current instanceof Authenticatable ? $current : null;
    }
}
