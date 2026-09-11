<?php

declare(strict_types=1);

namespace AIArmada\Communications\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

final class DispatchManagedNotificationJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public readonly mixed $notifiable,
        public readonly Notification $notification,
        public readonly array $channels,
        public readonly ?string $ownerType = null,
        public readonly string | int | null $ownerId = null,
        public readonly bool $ownerIsGlobal = false,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        NotificationFacade::sendNow(
            $this->notifiable,
            $this->notification,
            $this->channels,
        );
    }

    public function failed(Throwable $exception): void
    {
        if (method_exists($this->notification, 'failed')) {
            $this->notification->failed($exception);
        }
    }
}
