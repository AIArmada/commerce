<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method AffiliatePayout getModel()
 */
abstract class PayoutStatus extends State
{
    public static string $name = '';

    abstract public function label(): string;

    abstract public function color(): string;

    public static function value(): string
    {
        return static::$name;
    }

    public static function normalize(string | PayoutStatus $status): string
    {
        if ($status instanceof PayoutStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, PayoutStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new AffiliatePayout;

        $options = [];

        /** @var class-string<PayoutStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    public static function labelFor(string | PayoutStatus $status, ?Model $model = null): string
    {
        if ($status instanceof PayoutStatus) {
            return $status->label();
        }

        $model ??= new AffiliatePayout;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->label();
    }

    public static function colorFor(string | PayoutStatus $status, ?Model $model = null): string
    {
        if ($status instanceof PayoutStatus) {
            return $status->color();
        }

        $model ??= new AffiliatePayout;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->color();
    }

    public static function fromString(string | PayoutStatus $status, ?Model $model = null): PayoutStatus
    {
        if ($status instanceof PayoutStatus) {
            return $status;
        }

        $model ??= new AffiliatePayout;
        $stateClass = self::resolveStateClassFor($status, $model);

        return new $stateClass($model);
    }

    /**
     * @return class-string<PayoutStatus>
     */
    public static function resolveStateClassFor(string | PayoutStatus $status, ?Model $model = null): string
    {
        if ($status instanceof PayoutStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, PayoutStatus::class)) {
            return $status;
        }

        $model ??= new AffiliatePayout;

        /** @var class-string<PayoutStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            if ($state->getValue() === $status) {
                return $stateClass;
            }
        }

        return PendingPayout::class;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingPayout::class)
            ->allowTransition(PendingPayout::class, ProcessingPayout::class)
            ->allowTransition(PendingPayout::class, CompletedPayout::class)
            ->allowTransition(PendingPayout::class, FailedPayout::class)
            ->allowTransition(PendingPayout::class, CancelledPayout::class)
            ->allowTransition(ProcessingPayout::class, CompletedPayout::class)
            ->allowTransition(ProcessingPayout::class, FailedPayout::class)
            ->allowTransition(ProcessingPayout::class, CancelledPayout::class);
    }
}
