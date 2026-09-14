<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Server-side validation for ticketable references submitted through
 * Filament forms: the registry allow-list plus an owner-scoped existence
 * check per selected id.
 */
final class TicketableReferenceGuard
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $type = $data['ticketable_type'] ?? null;
        $id = $data['ticketable_id'] ?? null;

        if (! is_string($type) || $type === '' || ! is_scalar($id) || (string) $id === '') {
            throw ValidationException::withMessages([
                'ticketable' => 'Select a ticketable record.',
            ]);
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! in_array($class, app(TicketableTypeRegistry::class)->all(), true)) {
            throw ValidationException::withMessages([
                'ticketable' => 'The selected ticketable type is not allowed.',
            ]);
        }

        /** @var Builder<Model> $query */
        $query = $class::query();

        $exists = OwnerUiScope::apply($query, includeGlobal: false)
            ->whereKey((string) $id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'ticketable' => 'The selected ticketable record is not accessible.',
            ]);
        }

        return $data;
    }

    /**
     * Columns that are safe to search for the given ticketable type: only
     * columns present on the type's table, so a type missing one of the
     * conventional labels cannot produce a SQL error.
     *
     * @param  class-string<Model>  $class
     * @return list<string>
     */
    public static function searchColumnsFor(string $class): array
    {
        try {
            $model = new $class;
            $table = $model->getTable();
        } catch (Throwable) {
            return [];
        }

        $columns = [];

        foreach (['name', 'code', 'title'] as $column) {
            try {
                if (Schema::hasColumn($table, $column)) {
                    $columns[] = $column;
                }
            } catch (Throwable) {
                continue;
            }
        }

        if ($columns === []) {
            try {
                $keyName = $model->getKeyName();

                if (Schema::hasColumn($table, $keyName)) {
                    $columns[] = $keyName;
                }
            } catch (Throwable) {
                // Leave the list empty; the picker still scopes options.
            }
        }

        return $columns;
    }

    /**
     * @param  class-string<Model>  $class
     */
    public static function titleAttributeFor(string $class): string
    {
        $columns = self::searchColumnsFor($class);

        if ($columns !== []) {
            return $columns[0];
        }

        try {
            return (new $class)->getKeyName();
        } catch (Throwable) {
            return 'id';
        }
    }
}
