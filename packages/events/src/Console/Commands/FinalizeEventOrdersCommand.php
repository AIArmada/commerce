<?php

declare(strict_types=1);

namespace AIArmada\Events\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\FinalizeOccurredEventOrdersAction;
use AIArmada\Events\Models\EventOccurrence;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class FinalizeEventOrdersCommand extends Command
{
    protected $signature = 'events:finalize-orders
        {--occurrence= : The occurrence ID to finalize}
        {--owner-type= : The owner model class for scoped execution}
        {--owner-id= : The owner key for scoped execution}
        {--global : Run across all owners in explicit global context}
        {--dry-run : Perform a dry run without making changes}';

    protected $description = 'Finalize orders for completed event occurrences';

    public function handle(FinalizeOccurredEventOrdersAction $action): int
    {
        $owner = $this->resolveOwnerOption();

        if ($owner === false) {
            $this->error('Provide --owner-type and --owner-id together, or --global, or run with a resolved owner context.');

            return self::FAILURE;
        }

        if ($owner instanceof Model) {
            return OwnerContext::withOwner($owner, fn (): int => $this->runFinalize($action));
        }

        if ((bool) $this->option('global')) {
            return OwnerContext::withOwner(null, fn (): int => $this->runFinalize($action));
        }

        if (OwnerContext::resolve() !== null) {
            return $this->runFinalize($action);
        }

        $this->error('No owner context is available. Provide --owner-type and --owner-id, or --global.');

        return self::FAILURE;
    }

    private function runFinalize(FinalizeOccurredEventOrdersAction $action): int
    {
        $occurrenceId = $this->option('occurrence');
        $dryRun = (bool) $this->option('dry-run');
        $finalized = 0;

        EventOccurrence::query()
            ->where('status', EventOccurrence::COMPLETED)
            ->when($occurrenceId !== null, fn ($query) => $query->whereKey($occurrenceId))
            ->chunkById(200, function ($occurrences) use ($action, $dryRun, &$finalized): void {
                foreach ($occurrences as $occurrence) {
                    if ($dryRun) {
                        $this->line(sprintf('[DRY RUN] Would finalize orders for occurrence %s', $occurrence->getKey()));

                        continue;
                    }

                    $action->handle($occurrence);
                    $finalized++;

                    $this->line(sprintf('Finalized orders for occurrence %s', $occurrence->getKey()));
                }
            });

        if ($finalized === 0) {
            $this->info($dryRun ? 'No completed occurrences found.' : 'No completed occurrences finalized.');
        } else {
            $this->info(sprintf('Finalized orders for %d completed occurrence(s).', $finalized));
        }

        return self::SUCCESS;
    }

    /**
     * Returns the requested owner, null for explicit global or ambient
     * context, or false when the owner options are inconsistent.
     */
    private function resolveOwnerOption(): Model | null | false
    {
        $ownerType = $this->option('owner-type');
        $ownerId = $this->option('owner-id');
        $global = (bool) $this->option('global');

        if ($global && ($ownerType !== null || $ownerId !== null)) {
            return false;
        }

        if ($global) {
            return null;
        }

        if ($ownerType === null && $ownerId === null) {
            return null;
        }

        if (! is_string($ownerType) || $ownerType === '' || $ownerId === null || $ownerId === '') {
            return false;
        }

        $ownerClass = Relation::getMorphedModel($ownerType) ?? $ownerType;

        if (! is_string($ownerClass) || ! class_exists($ownerClass)) {
            throw new InvalidArgumentException(sprintf('Owner type "%s" could not be resolved to a model class.', $ownerType));
        }

        $owner = $ownerClass::query()->whereKey($ownerId)->first();

        if (! $owner instanceof Model) {
            throw new InvalidArgumentException(sprintf('Owner "%s" with key "%s" was not found.', $ownerType, (string) $ownerId));
        }

        return $owner;
    }
}
