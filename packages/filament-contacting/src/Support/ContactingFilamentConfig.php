<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Support;

final class ContactingFilamentConfig
{
    public function navigationGroup(): ?string
    {
        return config('filament-contacting.navigation.group');
    }

    public function navigationSort(): int
    {
        return (int) config('filament-contacting.navigation.sort', 70);
    }

    public function navigationIcon(string $resource): string
    {
        return (string) config('filament-contacting.navigation.icons.' . $resource, 'heroicon-o-circle');
    }

    public function standaloneResources(): bool
    {
        return (bool) config('filament-contacting.features.standalone_resources', false);
    }

    public function relationManagersEnabled(): bool
    {
        return (bool) config('filament-contacting.features.relation_managers', true);
    }

    public function exportsEnabled(): bool
    {
        return (bool) config('filament-contacting.features.exports', true);
    }

    public function importsEnabled(): bool
    {
        return (bool) config('filament-contacting.features.imports', false);
    }

    public function verificationBadges(): bool
    {
        return (bool) config('filament-contacting.features.verification_badges', true);
    }

    public function openUrlActions(): bool
    {
        return (bool) config('filament-contacting.features.open_url_actions', true);
    }

    public function showOwnerColumns(): bool
    {
        return (bool) config('filament-contacting.tables.show_owner_columns', false);
    }

    public function showVerificationColumns(): bool
    {
        return (bool) config('filament-contacting.tables.show_verification_columns', true);
    }

    public function showVisibilityColumns(): bool
    {
        return (bool) config('filament-contacting.tables.show_visibility_columns', true);
    }

    public function defaultPagination(): int
    {
        return (int) config('filament-contacting.tables.default_pagination', 25);
    }
}
