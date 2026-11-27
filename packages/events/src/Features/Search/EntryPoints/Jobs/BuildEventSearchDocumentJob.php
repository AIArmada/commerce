<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Features\Search\Actions\DeleteEventSearchDocumentAction;
use AIArmada\Events\Features\Search\Actions\UpsertEventSearchDocumentAction;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
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

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    public function __construct(
        public readonly string $targetType,
        public readonly string $targetId,
        public readonly ?string $ownerType,
        public readonly string | int | null $ownerId,
        public readonly bool $ownerIsGlobal,
    ) {}

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

        $targetClass = $this->resolveTargetClass();

        if ($targetClass === null) {
            return;
        }

        $target = $targetClass::query()
            ->with(app(EventSearchRelationProvider::class)->relations())
            ->find($this->targetId);

        if (! $target instanceof Model) {
            app(DeleteEventSearchDocumentAction::class)->handle($targetClass, $this->targetId);

            return;
        }

        app(UpsertEventSearchDocumentAction::class)->handleTarget($target, $targetClass);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to build event search document.', [
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'message' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveTargetClass(): ?string
    {
        $classes = [
            ModelResolver::eventClass(),
            ModelResolver::occurrenceClass(),
            ModelResolver::sessionClass(),
        ];

        foreach ($classes as $class) {
            if (is_a($this->targetType, $class, true)) {
                return $class;
            }
        }

        return null;
    }
}
