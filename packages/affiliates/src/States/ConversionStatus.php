<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method AffiliateConversion getModel()
 */
abstract class ConversionStatus extends State
{
    public static string $name = '';

    abstract public function label(): string;

    abstract public function color(): string;

    public static function value(): string
    {
        return static::$name;
    }

    public static function normalize(string | ConversionStatus $status): string
    {
        if ($status instanceof ConversionStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, ConversionStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new AffiliateConversion;

        $options = [];

        /** @var class-string<ConversionStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    public static function labelFor(string | ConversionStatus $status, ?Model $model = null): string
    {
        if ($status instanceof ConversionStatus) {
            return $status->label();
        }

        $model ??= new AffiliateConversion;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->label();
    }

    public static function colorFor(string | ConversionStatus $status, ?Model $model = null): string
    {
        if ($status instanceof ConversionStatus) {
            return $status->color();
        }

        $model ??= new AffiliateConversion;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->color();
    }

    public static function fromString(string | ConversionStatus $status, ?Model $model = null): ConversionStatus
    {
        if ($status instanceof ConversionStatus) {
            return $status;
        }

        $model ??= new AffiliateConversion;
        $stateClass = self::resolveStateClassFor($status, $model);

        return new $stateClass($model);
    }

    /**
     * @return class-string<ConversionStatus>
     */
    public static function resolveStateClassFor(string | ConversionStatus $status, ?Model $model = null): string
    {
        if ($status instanceof ConversionStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, ConversionStatus::class)) {
            return $status;
        }

        $model ??= new AffiliateConversion;

        /** @var class-string<ConversionStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            if ($state->getValue() === $status) {
                return $stateClass;
            }
        }

        return PendingConversion::class;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingConversion::class)
            ->allowTransition(PendingConversion::class, QualifiedConversion::class)
            ->allowTransition(PendingConversion::class, ApprovedConversion::class)
            ->allowTransition(PendingConversion::class, RejectedConversion::class)
            ->allowTransition(QualifiedConversion::class, ApprovedConversion::class)
            ->allowTransition(QualifiedConversion::class, RejectedConversion::class)
            ->allowTransition(ApprovedConversion::class, PaidConversion::class)
            ->allowTransition(ApprovedConversion::class, RejectedConversion::class);
    }
}
