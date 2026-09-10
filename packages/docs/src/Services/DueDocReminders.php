<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\CommerceSupport\Support\OwnerTuple\ParsedOwnerTuple;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Owns due-document queries and owner batching for reminder delivery.
 */
final class DueDocReminders
{
    /**
     * @return EloquentCollection<int, Doc>
     */
    public function dueSoon(int $daysBeforeDue): EloquentCollection
    {
        $dueDate = CarbonImmutable::now()->addDays($daysBeforeDue);

        return $this->forCurrentOwner()
            ->whereIn('status', [Sent::value(), Pending::value()])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $dueDate->toDateString())
            ->whereDoesntHave('emails', function (Builder $query): void {
                $query->where('metadata->reminder_type', 'due_soon');
            })
            ->whereJsonContainsKey('customer_data->email')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Doc>
     */
    public function overdue(int $daysAfterOverdue): EloquentCollection
    {
        $overdueDate = CarbonImmutable::now()->subDays($daysAfterOverdue);

        return $this->forCurrentOwner()
            ->where('status', Overdue::value())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $overdueDate->toDateString())
            ->whereDoesntHave('emails', function (Builder $query): void {
                $query->where('metadata->reminder_type', 'overdue');
            })
            ->whereJsonContainsKey('customer_data->email')
            ->get();
    }

    /**
     * @return Builder<Doc>
     */
    public function forCurrentOwner(): Builder
    {
        $query = Doc::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        return $query->forOwner(
            OwnerContext::resolve(),
            (bool) config('docs.owner.include_global', false),
        );
    }

    /**
     * Enumerate the owner tuples that have documents for reminder fan-out.
     *
     * @return Collection<int, ParsedOwnerTuple>
     */
    public function ownerTuples(): Collection
    {
        $columns = OwnerTupleColumns::forModelClass(Doc::class);

        return Doc::query()
            ->withoutOwnerScope()
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get()
            ->map(static fn (object $row): ParsedOwnerTuple => OwnerTupleParser::fromRow($row, $columns));
    }
}
