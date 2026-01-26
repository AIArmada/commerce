<?php

declare(strict_types=1);

namespace AIArmada\Docs\States;

use AIArmada\Docs\Models\Doc;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method Doc getModel()
 */
abstract class DocStatus extends State
{
    public static string $name = '';

    abstract public function label(): string;

    abstract public function color(): string;

    public function isPayable(): bool
    {
        return false;
    }

    public function isPaid(): bool
    {
        return false;
    }

    public static function value(): string
    {
        return static::$name;
    }

    public static function normalize(string | DocStatus $status): string
    {
        if ($status instanceof DocStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, DocStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new Doc();

        $options = [];

        /** @var class-string<DocStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    public static function labelFor(string | DocStatus $status, ?Model $model = null): string
    {
        if ($status instanceof DocStatus) {
            return $status->label();
        }

        $model ??= new Doc();
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->label();
    }

    public static function colorFor(string | DocStatus $status, ?Model $model = null): string
    {
        if ($status instanceof DocStatus) {
            return $status->color();
        }

        $model ??= new Doc();
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->color();
    }

    public static function fromString(string | DocStatus $status, ?Model $model = null): DocStatus
    {
        if ($status instanceof DocStatus) {
            return $status;
        }

        $model ??= new Doc();
        $stateClass = self::resolveStateClassFor($status, $model);

        return new $stateClass($model);
    }

    /**
     * @return class-string<DocStatus>
     */
    public static function resolveStateClassFor(string | DocStatus $status, ?Model $model = null): string
    {
        if ($status instanceof DocStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, DocStatus::class)) {
            return $status;
        }

        $model ??= new Doc();

        /** @var class-string<DocStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            if ($state->getValue() === $status) {
                return $stateClass;
            }
        }

        return Draft::class;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class)
            ->allowTransition(Draft::class, Sent::class)
            ->allowTransition(Draft::class, Cancelled::class)
            ->allowTransition(Pending::class, Sent::class)
            ->allowTransition(Pending::class, PartiallyPaid::class)
            ->allowTransition(Pending::class, Paid::class)
            ->allowTransition(Pending::class, Overdue::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Sent::class, PartiallyPaid::class)
            ->allowTransition(Sent::class, Paid::class)
            ->allowTransition(Sent::class, Overdue::class)
            ->allowTransition(Sent::class, Cancelled::class)
            ->allowTransition(PartiallyPaid::class, Paid::class)
            ->allowTransition(PartiallyPaid::class, Overdue::class)
            ->allowTransition(PartiallyPaid::class, Cancelled::class)
            ->allowTransition(Overdue::class, PartiallyPaid::class)
            ->allowTransition(Overdue::class, Paid::class)
            ->allowTransition(Overdue::class, Cancelled::class)
            ->allowTransition(Paid::class, Refunded::class);
    }
}