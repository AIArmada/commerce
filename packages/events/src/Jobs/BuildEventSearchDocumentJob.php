<?php

declare(strict_types=1);

namespace AIArmada\Events\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Services\EventSearchDocumentBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BuildEventSearchDocumentJob implements OwnerScopedJob, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    public readonly string $targetType;

    public readonly string $targetId;

    public readonly ?string $ownerType;

    public readonly string | int | null $ownerId;

    public readonly bool $ownerIsGlobal;

    public function __construct(
        Event | EventOccurrence | EventSession $target,
    ) {
        $event = $target instanceof Event ? $target : $target->event;
        $owner = $event->owner;

        $this->targetType = $target::class;
        $this->targetId = (string) $target->getKey();
        $this->ownerType = $owner?->getMorphClass();
        $this->ownerId = $owner?->getKey();
        $this->ownerIsGlobal = $owner === null;
    }

    public function uniqueId(): string
    {
        return 'build_event_search_doc_' . mb_strtolower(class_basename($this->targetType)) . '_' . $this->targetId;
    }

    public function uniqueFor(): int
    {
        return 60;
    }

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
        if (! config('events.sync.build_search_documents')) {
            return;
        }

        $target = match ($this->targetType) {
            Event::class => Event::query()->find($this->targetId),
            EventOccurrence::class => EventOccurrence::query()->find($this->targetId),
            EventSession::class => EventSession::query()->find($this->targetId),
        };

        if (! $target instanceof Event && ! $target instanceof EventOccurrence && ! $target instanceof EventSession) {
            return;
        }

        app(EventSearchDocumentBuilder::class)->index($target);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to build event search document.', [
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
