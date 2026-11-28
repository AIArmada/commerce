<?php

declare(strict_types=1);

namespace AIArmada\Events\Platform;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class SharedCatalogPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return true;
    }

    public function view(Authenticatable $actor, Model $record): bool
    {
        return true;
    }

    public function create(Authenticatable $actor): bool
    {
        return Gate::forUser($actor)->allows('manageSharedEventCatalog');
    }

    public function update(Authenticatable $actor, Model $record): bool
    {
        return $this->create($actor);
    }

    public function delete(Authenticatable $actor, Model $record): bool
    {
        return $this->create($actor);
    }

    public function deleteAny(Authenticatable $actor): bool
    {
        return $this->create($actor);
    }
}
