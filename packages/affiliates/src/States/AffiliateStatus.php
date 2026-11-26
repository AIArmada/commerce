<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

use InvalidArgumentException;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class AffiliateStatus extends State
{
    abstract public static function label(): string;

    abstract public static function description(): string;

    abstract public static function color(): string;

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
    public static function options(): array
    {
        $options = [];

        /** @var class-string<AffiliateStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $options[$stateClass::getMorphClass()] = $stateClass::label();
        }

        return $options;
    }

    public static function labelFor(string | AffiliateStatus $status): string
    {
        return self::resolveStateClassFor($status)::label();
    }

    public static function descriptionFor(string | AffiliateStatus $status): string
    {
        return self::resolveStateClassFor($status)::description();
    }

    public static function colorFor(string | AffiliateStatus $status): string
    {
        return self::resolveStateClassFor($status)::color();
    }

    /** @return class-string<AffiliateStatus> */
    private static function resolveStateClassFor(string | AffiliateStatus $status): string
    {
        if ($status instanceof AffiliateStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, AffiliateStatus::class)) {
            return $status;
        }

        /** @var class-string<AffiliateStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            if ($stateClass::getMorphClass() === $status) {
                return $stateClass;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown affiliate status [%s].', $status));
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
