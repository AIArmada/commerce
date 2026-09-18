<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Support;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\ResolutionGap;
use Illuminate\Support\Facades\Cache;

/**
 * Short-TTL caches for table filter dropdowns over large reference tables.
 *
 * Area datasets can be large, so the distinct scans behind the area and
 * country filters are cached for a few minutes instead of running on every
 * table render. Callers mutate through the Filament UI should call
 * forgetAreaOptions() after writes; imports do so when the completion
 * notification is built.
 */
final class AddressingFilterOptions
{
    private const int TTL_SECONDS = 300;

    /** @return array<string, string> */
    public static function areaTypes(): array
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        return Cache::remember(
            self::key('area-types', $areaClass),
            self::TTL_SECONDS,
            static fn (): array => $areaClass::query()
                ->distinct()
                ->orderBy('type')
                ->pluck('type', 'type')
                ->toArray(),
        );
    }

    /** @return array<string, string> */
    public static function areaLevels(): array
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        return Cache::remember(
            self::key('area-levels', $areaClass),
            self::TTL_SECONDS,
            static fn (): array => $areaClass::query()
                ->whereNotNull('level')
                ->distinct()
                ->orderBy('level')
                ->pluck('level', 'level')
                ->map(static fn (mixed $level): string => (string) $level)
                ->toArray(),
        );
    }

    /** @return array<string, string> */
    public static function areaSources(): array
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        return Cache::remember(
            self::key('area-sources', $areaClass),
            self::TTL_SECONDS,
            static fn (): array => $areaClass::query()
                ->distinct()
                ->orderBy('source')
                ->pluck('source', 'source')
                ->toArray(),
        );
    }

    /** @return array<string, string> */
    public static function areaRoles(): array
    {
        return Cache::remember(
            self::key('area-roles', AddressAreaRole::class),
            self::TTL_SECONDS,
            static fn (): array => AddressAreaRole::query()
                ->distinct()
                ->orderBy('role')
                ->pluck('role', 'role')
                ->mapWithKeys(static fn (string $role): array => [$role => str_replace('_', ' ', ucfirst($role))])
                ->all(),
        );
    }

    /** @return array<string, string> */
    public static function gapRoles(): array
    {
        $gapClass = config('filament-addressing.resources.resolution_gaps.model', ResolutionGap::class);

        return Cache::remember(
            self::key('gap-roles', $gapClass),
            self::TTL_SECONDS,
            static fn (): array => $gapClass::query()
                ->distinct()
                ->orderBy('role')
                ->pluck('role', 'role')
                ->mapWithKeys(static fn (string $role): array => [$role => str_replace('_', ' ', ucfirst($role))])
                ->all(),
        );
    }

    /** @return array<string, string> */
    public static function countryOptions(): array
    {
        $countryClass = config('filament-addressing.resources.countries.model', AddressCountry::class);

        return Cache::remember(
            self::key('country-options', $countryClass),
            self::TTL_SECONDS,
            static fn (): array => $countryClass::query()
                ->orderBy('name')
                ->pluck('name', 'iso2')
                ->toArray(),
        );
    }

    /** @return array<string, string> */
    public static function countryRegions(): array
    {
        $countryClass = config('filament-addressing.resources.countries.model', AddressCountry::class);

        return Cache::remember(
            self::key('country-regions', $countryClass),
            self::TTL_SECONDS,
            static fn (): array => $countryClass::query()
                ->whereNotNull('region')
                ->distinct()
                ->orderBy('region')
                ->pluck('region', 'region')
                ->toArray(),
        );
    }

    public static function forgetAreaOptions(): void
    {
        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);

        Cache::forget(self::key('area-types', $areaClass));
        Cache::forget(self::key('area-levels', $areaClass));
        Cache::forget(self::key('area-sources', $areaClass));
        Cache::forget(self::key('area-roles', AddressAreaRole::class));
    }

    private static function key(string $name, string $modelClass): string
    {
        return 'filament-addressing:filter-options:' . $name . ':' . md5($modelClass);
    }
}
