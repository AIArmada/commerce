<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Inbox\EntryPoints\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Communications\Features\Inbox\Actions\ManageInboxEntries;
use AIArmada\Communications\Features\Inbox\Models\NotificationInbox;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

final class PruneNotificationInboxesCommand extends Command
{
    protected $signature = 'communications:prune-inboxes
        {--owner= : Owner morph class and ID (e.g., "App\Models\Team:1")}
        {--all-owners : Prune every distinct owner scope and global rows}
        {--before= : Prune inboxes archived before this date}
        {--dry-run : Preview without deleting}';

    protected $description = 'Prune old archived notification inbox entries';

    public function __construct(
        private readonly ManageInboxEntries $inboxService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $allOwners = (bool) $this->option('all-owners');
        $ownerOption = $this->option('owner');

        if ($ownerOption !== null && ($allOwners || ! NotificationInbox::ownerScopeConfig()->enabled)) {
            $this->error('--owner requires inbox owner scoping and cannot be combined with --all-owners.');

            return self::FAILURE;
        }

        try {
            $before = $this->option('before')
                ? CarbonImmutable::parse($this->option('before'))
                : CarbonImmutable::now()->subDays(90);
        } catch (Throwable) {
            $this->error('Invalid --before date. Use a parseable date such as "2026-01-01".');

            return self::FAILURE;
        }

        try {
            if ($allOwners && NotificationInbox::ownerScopeConfig()->enabled) {
                return $this->pruneAllOwners($before);
            }

            return OwnerContext::withOwner(
                owner: $this->resolveOwner(),
                callback: fn (): int => $this->pruneScope($before),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function pruneScope(CarbonImmutable $before): int
    {
        $this->info("Pruning inboxes archived before: {$before->toDateTimeString()}");

        if ($this->option('dry-run')) {
            $count = $this->inboxService->countPrunable($before);

            $this->info("Would prune {$count} inbox entries.");

            return self::SUCCESS;
        }

        $pruned = $this->inboxService->prune($before);

        $this->info("Pruned {$pruned} inbox entries.");

        return self::SUCCESS;
    }

    private function pruneAllOwners(CarbonImmutable $before): int
    {
        $columns = OwnerTupleColumns::forModelClass(NotificationInbox::class);

        $owners = OwnerContext::withOwner(null, fn (): mixed => NotificationInbox::query()
            ->withoutOwnerScope()
            ->select([
                $columns->ownerTypeColumn . ' as owner_type',
                $columns->ownerIdColumn . ' as owner_id',
            ])
            ->distinct()
            ->get());

        $pruned = 0;

        foreach ($owners as $row) {
            $tuple = OwnerTupleParser::fromRow($row, new OwnerTupleColumns);
            $owner = $tuple->toOwnerModel();

            $pruned += OwnerContext::withOwner(
                $owner,
                fn (): int => $this->option('dry-run')
                    ? $this->inboxService->countPrunable($before)
                    : $this->inboxService->prune($before),
            );
        }

        $this->info($this->option('dry-run')
            ? "Would prune {$pruned} inbox entries across all owners."
            : "Pruned {$pruned} inbox entries across all owners.");

        return self::SUCCESS;
    }

    private function resolveOwner(): ?Model
    {
        $owner = $this->option('owner');

        if ($owner === null) {
            return null;
        }

        if (str_contains($owner, ':')) {
            [$type, $id] = explode(':', $owner, 2);

            return OwnerContext::fromTypeAndId($type, $id);
        }

        throw new InvalidArgumentException('Invalid --owner format. Use "TypeClass:id" (e.g. "App\Models\Team:1").');
    }
}
