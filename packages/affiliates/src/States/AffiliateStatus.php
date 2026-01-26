<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method Affiliate getModel()
 */
abstract class AffiliateStatus extends State
{
    abstract public function label(): string;

    abstract public function description(): string;

    public function isDraft(): bool
    {
        return false;
    }

    public function isPending(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return false;
    }

    public function isPaused(): bool
    {
        return false;
    }

    public function isDisabled(): bool
    {
        return false;
    }

    public static function normalize(string | AffiliateStatus $status): string
    {
        if ($status instanceof AffiliateStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, AffiliateStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new Affiliate;

        $options = [];

        /** @var class-string<AffiliateStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    public static function labelFor(string | AffiliateStatus $status, ?Model $model = null): string
    {
        if ($status instanceof AffiliateStatus) {
            return $status->label();
        }

        $model ??= new Affiliate;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->label();
    }

    public static function descriptionFor(string | AffiliateStatus $status, ?Model $model = null): string
    {
        if ($status instanceof AffiliateStatus) {
            return $status->description();
        }

        $model ??= new Affiliate;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->description();
    }

    public static function fromString(string | AffiliateStatus $status, ?Model $model = null): AffiliateStatus
    {
        if ($status instanceof AffiliateStatus) {
            return $status;
        }

        $model ??= new Affiliate;
        $stateClass = self::resolveStateClassFor($status, $model);

        return new $stateClass($model);
    }

    /**
     * @return class-string<AffiliateStatus>
     */
    public static function resolveStateClassFor(string | AffiliateStatus $status, ?Model $model = null): string
    {
        if ($status instanceof AffiliateStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, AffiliateStatus::class)) {
            return $status;
        }

        $model ??= new Affiliate;

        /** @var class-string<AffiliateStatus> $stateClass */
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
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Draft::class, Disabled::class)
            ->allowTransition(Pending::class, Active::class)
            ->allowTransition(Pending::class, Disabled::class)
            ->allowTransition(Active::class, Paused::class)
            ->allowTransition(Active::class, Disabled::class)
            ->allowTransition(Paused::class, Active::class)
            ->allowTransition(Paused::class, Disabled::class)
            ->allowTransition(Disabled::class, Active::class);
    }
}
