<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;

trait HasOwnerScopeConfig
{
    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        $key = property_exists(static::class, 'ownerScopeConfigKey')
            ? static::$ownerScopeConfigKey
            : '';
        $enabledByDefault = property_exists(static::class, 'ownerScopeEnabledByDefault')
            ? static::$ownerScopeEnabledByDefault
            : false;
        $includeGlobalByDefault = property_exists(static::class, 'ownerScopeIncludeGlobalByDefault')
            ? static::$ownerScopeIncludeGlobalByDefault
            : false;
        $ownerTypeColumn = property_exists(static::class, 'ownerScopeOwnerTypeColumn')
            ? static::$ownerScopeOwnerTypeColumn
            : 'owner_type';
        $ownerIdColumn = property_exists(static::class, 'ownerScopeOwnerIdColumn')
            ? static::$ownerScopeOwnerIdColumn
            : 'owner_id';

        if ($key === '') {
            return new OwnerScopeConfig(
                enabled: false,
                includeGlobal: false,
                owner: null,
                ownerTypeColumn: $ownerTypeColumn,
                ownerIdColumn: $ownerIdColumn,
            );
        }

        return OwnerScopeConfig::fromConfig(
            $key,
            $enabledByDefault,
            $includeGlobalByDefault,
            null,
            $ownerTypeColumn,
            $ownerIdColumn,
        );
    }
}
