<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

final class TicketablePickerColumns
{
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
            $schema = $model->getConnection()->getSchemaBuilder();
        } catch (Throwable) {
            return [];
        }

        $columns = [];

        foreach (['name', 'code', 'title'] as $column) {
            try {
                if ($schema->hasColumn($table, $column)) {
                    $columns[] = $column;
                }
            } catch (Throwable) {
                continue;
            }
        }

        if ($columns === []) {
            try {
                $keyName = $model->getKeyName();

                if ($schema->hasColumn($table, $keyName)) {
                    $columns[] = $keyName;
                }
            } catch (Throwable) {
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
