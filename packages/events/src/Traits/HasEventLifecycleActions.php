<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use DateTimeInterface;

trait HasEventLifecycleActions
{
    public function publish(): void
    {
        $this->lifecycleWorkflow()->publish($this);
    }

    public function cancel(?string $reason = null): void
    {
        $this->lifecycleWorkflow()->cancel($this, $reason);
    }

    public function postpone(?string $reason = null): void
    {
        $this->lifecycleWorkflow()->postpone($this, $reason);
    }

    public function delay(?string $reason = null, ?DateTimeInterface $expectedStartsAt = null): void
    {
        $this->lifecycleWorkflow()->delay($this, $reason, $expectedStartsAt);
    }

    public function reschedule(DateTimeInterface $startsAt, DateTimeInterface $endsAt, array $options = []): mixed
    {
        return $this->lifecycleWorkflow()->reschedule($this, $startsAt, $endsAt, $options);
    }

    public function complete(): void
    {
        $this->lifecycleWorkflow()->complete($this);
    }

    public function archive(?string $reason = null): void
    {
        $this->lifecycleWorkflow()->archive($this, $reason);
    }

    private function lifecycleWorkflow(): EventLifecycleWorkflow
    {
        return app(EventLifecycleWorkflow::class);
    }
}
