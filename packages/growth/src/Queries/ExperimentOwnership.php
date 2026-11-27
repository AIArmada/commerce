<?php

declare(strict_types=1);

namespace AIArmada\Growth\Queries;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ExperimentOwnership
{
    /**
     * @param  Builder<Experiment>  $query
     * @return Builder<Experiment>
     */
    public static function scopeExperiments(Builder $query, bool $writable = false): Builder
    {
        $query = self::scopeOwner($query, $writable);

        if (! Experiment::ownerScopeConfig()->enabled) {
            if (! TrackedProperty::ownerScopeConfig()->enabled) {
                return $query;
            }

            return $query->whereIn(
                $query->getModel()->qualifyColumn('tracked_property_id'),
                self::scopeOwner(TrackedProperty::query(), $writable)->select('id'),
            );
        }

        $experiment = $query->getModel();
        $property = new TrackedProperty;
        $experimentColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $propertyColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);
        $propertyType = $property->qualifyColumn($propertyColumns->ownerTypeColumn);
        $propertyId = $property->qualifyColumn($propertyColumns->ownerIdColumn);
        $experimentType = $experiment->qualifyColumn($experimentColumns->ownerTypeColumn);
        $experimentId = $experiment->qualifyColumn($experimentColumns->ownerIdColumn);

        $matched = TrackedProperty::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->selectRaw('1')
            ->whereColumn($property->getQualifiedKeyName(), $experiment->qualifyColumn('tracked_property_id'))
            ->where(function (Builder $query) use ($propertyType, $propertyId, $experimentType, $experimentId): void {
                $query->where(function (Builder $owned) use ($propertyType, $propertyId, $experimentType, $experimentId): void {
                    $owned->whereColumn($propertyType, $experimentType)
                        ->whereColumn($propertyId, $experimentId);
                })->orWhere(function (Builder $global) use ($propertyType, $propertyId, $experimentType, $experimentId): void {
                    $global->whereNull($propertyType)
                        ->whereNull($propertyId)
                        ->whereNull($experimentType)
                        ->whereNull($experimentId);
                });
            });

        return $query->whereExists($matched);
    }

    /**
     * @param  Builder<Variant>  $query
     * @return Builder<Variant>
     */
    public static function scopeVariants(Builder $query, bool $writable = false): Builder
    {
        $query = self::scopeOwner($query, $writable);
        $variant = $query->getModel();
        $experiment = new Experiment;
        $parents = self::scopeExperiments(Experiment::query(), $writable)
            ->selectRaw('1')
            ->whereColumn($experiment->getQualifiedKeyName(), $variant->qualifyColumn('experiment_id'));

        if (Variant::ownerScopeConfig()->enabled) {
            $variantColumns = OwnerTupleColumns::forModelClass(Variant::class);
            $experimentColumns = OwnerTupleColumns::forModelClass(Experiment::class);
            $variantType = $variant->qualifyColumn($variantColumns->ownerTypeColumn);
            $variantId = $variant->qualifyColumn($variantColumns->ownerIdColumn);
            $experimentType = $experiment->qualifyColumn($experimentColumns->ownerTypeColumn);
            $experimentId = $experiment->qualifyColumn($experimentColumns->ownerIdColumn);

            $parents->where(function (Builder $query) use ($variantType, $variantId, $experimentType, $experimentId): void {
                $query->where(function (Builder $owned) use ($variantType, $variantId, $experimentType, $experimentId): void {
                    $owned->whereColumn($variantType, $experimentType)
                        ->whereColumn($variantId, $experimentId);
                })->orWhere(function (Builder $global) use ($variantType, $variantId, $experimentType, $experimentId): void {
                    $global->whereNull($variantType)
                        ->whereNull($variantId)
                        ->whereNull($experimentType)
                        ->whereNull($experimentId);
                });
            });
        }

        return $query->whereExists($parents);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeOwner(Builder $query, bool $writable = false): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $query->getModel()::class;

        if (! method_exists($modelClass, 'ownerScopeConfig')) {
            throw new InvalidArgumentException('Growth owner queries require an owner-scoped model.');
        }

        /** @var OwnerScopeConfig $config */
        $config = $modelClass::ownerScopeConfig();

        if (! $config->enabled) {
            return $query;
        }

        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Growth requires an owner context or explicit global context.');

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            ! $writable && ! OwnerScopeOverride::suppressIncludeGlobal() && $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }
}
