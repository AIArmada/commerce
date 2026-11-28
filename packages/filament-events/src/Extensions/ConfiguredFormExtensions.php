<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Extensions;

use AIArmada\FilamentEvents\Contracts\EventFormExtension;
use AIArmada\FilamentEvents\Contracts\EventOccurrenceFormExtension;
use AIArmada\FilamentEvents\Contracts\TermFormExtension;
use Filament\Schemas\Components\Component;

/** Resolves host-configured UI extensions without sharing mutable components. */
final class ConfiguredFormExtensions
{
    /**
     * @param  class-string<EventFormExtension|EventOccurrenceFormExtension|TermFormExtension>  $contract
     * @return array<int, Component>
     */
    public static function components(string $configKey, string $contract): array
    {
        $configured = config($configKey, []);

        if (! is_array($configured)) {
            return [];
        }

        $components = [];
        $resolved = [];

        foreach ($configured as $extensionClass) {
            if (! is_string($extensionClass) || isset($resolved[$extensionClass]) || ! is_a($extensionClass, $contract, true)) {
                continue;
            }

            $resolved[$extensionClass] = true;
            $extension = app($extensionClass);

            foreach ($extension->components() as $component) {
                $components[] = $component;
            }
        }

        return $components;
    }
}
