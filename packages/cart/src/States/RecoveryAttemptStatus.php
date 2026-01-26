<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

use AIArmada\Cart\Models\RecoveryAttempt;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method RecoveryAttempt getModel()
 */
abstract class RecoveryAttemptStatus extends State
{
    abstract public function label(): string;

    public function isScheduled(): bool
    {
        return false;
    }

    public function isSent(): bool
    {
        return false;
    }

    public function isOpened(): bool
    {
        return false;
    }

    public function isClicked(): bool
    {
        return false;
    }

    public function isConverted(): bool
    {
        return false;
    }

    public function isFailed(): bool
    {
        return false;
    }

    public static function normalize(string | RecoveryAttemptStatus $status): string
    {
        if ($status instanceof RecoveryAttemptStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, RecoveryAttemptStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new RecoveryAttempt;

        $options = [];

        /** @var class-string<RecoveryAttemptStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(Scheduled::class)
            ->allowTransition(Scheduled::class, Queued::class)
            ->allowTransition(Scheduled::class, Sent::class)
            ->allowTransition(Scheduled::class, Failed::class)
            ->allowTransition(Scheduled::class, Bounced::class)
            ->allowTransition(Scheduled::class, Unsubscribed::class)
            ->allowTransition(Scheduled::class, Cancelled::class)
            ->allowTransition(Queued::class, Sent::class)
            ->allowTransition(Queued::class, Failed::class)
            ->allowTransition(Queued::class, Bounced::class)
            ->allowTransition(Queued::class, Unsubscribed::class)
            ->allowTransition(Queued::class, Cancelled::class)
            ->allowTransition(Sent::class, Delivered::class)
            ->allowTransition(Sent::class, Opened::class)
            ->allowTransition(Sent::class, Clicked::class)
            ->allowTransition(Sent::class, Converted::class)
            ->allowTransition(Sent::class, Failed::class)
            ->allowTransition(Sent::class, Bounced::class)
            ->allowTransition(Sent::class, Unsubscribed::class)
            ->allowTransition(Delivered::class, Opened::class)
            ->allowTransition(Delivered::class, Clicked::class)
            ->allowTransition(Delivered::class, Converted::class)
            ->allowTransition(Delivered::class, Failed::class)
            ->allowTransition(Delivered::class, Bounced::class)
            ->allowTransition(Delivered::class, Unsubscribed::class)
            ->allowTransition(Opened::class, Clicked::class)
            ->allowTransition(Opened::class, Converted::class)
            ->allowTransition(Clicked::class, Converted::class);
    }
}
