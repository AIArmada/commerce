<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class SavedSignalReportPolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('saved-signal-report.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('saved-signal-report.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('saved-signal-report.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('saved-signal-report.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('saved-signal-report.delete');
    }
}
