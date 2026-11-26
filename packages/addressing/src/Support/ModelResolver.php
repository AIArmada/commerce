<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Support;

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaAssignment;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Resolves host-application geography model subclasses configured for the package.
 */
final class ModelResolver
{
    /**
     * @return class-string<AddressCountry>
     */
    public static function countryClass(): string
    {
        /** @var class-string<AddressCountry> $modelClass */
        $modelClass = self::resolve('country', AddressCountry::class);

        return $modelClass;
    }

    /**
     * @return class-string<State>
     */
    public static function stateClass(): string
    {
        /** @var class-string<State> $modelClass */
        $modelClass = self::resolve('state', State::class);

        return $modelClass;
    }

    /**
     * @return class-string<City>
     */
    public static function cityClass(): string
    {
        /** @var class-string<City> $modelClass */
        $modelClass = self::resolve('city', City::class);

        return $modelClass;
    }

    /**
     * @return class-string<AddressArea>
     */
    public static function areaClass(): string
    {
        /** @var class-string<AddressArea> $modelClass */
        $modelClass = self::resolve('area', AddressArea::class);

        return $modelClass;
    }

    /**
     * @return class-string<Address>
     */
    public static function addressClass(): string
    {
        /** @var class-string<Address> $modelClass */
        $modelClass = self::resolve('address', Address::class);

        return $modelClass;
    }

    /**
     * @return class-string<Addressable>
     */
    public static function addressableClass(): string
    {
        /** @var class-string<Addressable> $modelClass */
        $modelClass = self::resolve('addressable', Addressable::class);

        return $modelClass;
    }

    /**
     * @return class-string<\AIArmada\Addressing\Features\PostalCodes\Models\PostalCode>
     */
    public static function postalCodeClass(): string
    {
        /** @var class-string<\AIArmada\Addressing\Features\PostalCodes\Models\PostalCode> $default */
        $default = app('addressing.model-default.postal_code');
        /** @var class-string<\AIArmada\Addressing\Features\PostalCodes\Models\PostalCode> $modelClass */
        $modelClass = self::resolve('postal_code', $default);

        return $modelClass;
    }

    /**
     * @return class-string<AddressAreaAssignment>
     */
    public static function areaAssignmentClass(): string
    {
        /** @var class-string<AddressAreaAssignment> $modelClass */
        $modelClass = self::resolve('area_assignment', AddressAreaAssignment::class);

        return $modelClass;
    }

    /**
     * @return class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaName>
     */
    public static function areaNameClass(): string
    {
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaName> $default */
        $default = app('addressing.model-default.area_name');
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaName> $modelClass */
        $modelClass = self::resolve('area_name', $default);

        return $modelClass;
    }

    /**
     * @return class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaRole>
     */
    public static function areaRoleClass(): string
    {
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaRole> $default */
        $default = app('addressing.model-default.area_role');
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaRole> $modelClass */
        $modelClass = self::resolve('area_role', $default);

        return $modelClass;
    }

    /**
     * @return class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaStateLink>
     */
    public static function areaStateLinkClass(): string
    {
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaStateLink> $default */
        $default = app('addressing.model-default.area_state_link');
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaStateLink> $modelClass */
        $modelClass = self::resolve('area_state_link', $default);

        return $modelClass;
    }

    /**
     * @return class-string<\AIArmada\Addressing\Features\PostalCodes\Models\AddressAreaPostalCode>
     */
    public static function areaPostalCodeClass(): string
    {
        /** @var class-string<\AIArmada\Addressing\Features\PostalCodes\Models\AddressAreaPostalCode> $default */
        $default = app('addressing.model-default.area_postal_code');
        /** @var class-string<\AIArmada\Addressing\Features\PostalCodes\Models\AddressAreaPostalCode> $modelClass */
        $modelClass = self::resolve('area_postal_code', $default);

        return $modelClass;
    }

    /**
     * @return class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaRelationship>
     */
    public static function areaRelationshipClass(): string
    {
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaRelationship> $default */
        $default = app('addressing.model-default.area_relationship');
        /** @var class-string<\AIArmada\Addressing\Features\Geography\Models\AddressAreaRelationship> $modelClass */
        $modelClass = self::resolve('area_relationship', $default);

        return $modelClass;
    }

    /**
     * @param  class-string<Model>  $default
     * @return class-string<Model>
     */
    private static function resolve(string $key, string $default): string
    {
        $modelClass = config("addressing.models.{$key}") ?? $default;

        if (! is_string($modelClass) || ! is_a($modelClass, $default, true)) {
            throw new LogicException(sprintf(
                'Addressing model [%s] must extend [%s] at [addressing.models.%s].',
                is_string($modelClass) ? $modelClass : get_debug_type($modelClass),
                $default,
                $key,
            ));
        }

        return $modelClass;
    }
}
