<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentPersonsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /* @phpstan-ignore return.type */
        return filament(app(self::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-persons';
    }

    public function register(Panel $panel): void
    {
        $this->registerNavigationGroup();
        $panel
            ->resources($this->getResources());
    }

    public function boot(Panel $panel): void {}

    private function registerNavigationGroup(): void
    {
        $group = config('filament-persons.navigation.group');
        $sort = config('filament-persons.navigation.sort');

        if (! is_string($group) || $group === '' || ! is_numeric($sort)) {
            return;
        }

        $groups = config('commerce-support.filament.navigation.groups', []);

        if (! is_array($groups)) {
            $groups = [];
        }

        $existing = $groups[$group] ?? [];

        if (is_string($existing)) {
            $existing = ['label' => $existing];
        }

        if (! is_array($existing)) {
            $existing = [];
        }

        $groups[$group] = [
            'label' => $existing['label'] ?? $group,
            ...$existing,
            'sort' => $existing['sort'] ?? (int) $sort,
        ];

        config()->set('commerce-support.filament.navigation.groups', $groups);
    }

    private function getResources(): array
    {
        $e = config('filament-persons.resources.enabled', []);
        $r = [];

        if ($e['person'] ?? true) {
            $r[] = Resources\PersonResource::class;
        }
        if ($e['title'] ?? true) {
            $r[] = Resources\TitleResource::class;
        }
        if ($e['title_issuer'] ?? true) {
            $r[] = Resources\TitleIssuerResource::class;
        }
        if ($e['credential_definition'] ?? true) {
            $r[] = Resources\CredentialDefinitionResource::class;
        }

        return $r;
    }
}
