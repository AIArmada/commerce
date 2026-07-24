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
        $panel
            ->resources($this->getResources());
    }

    public function boot(Panel $panel): void {}

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
