<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderFeaturedPersonsAction
{
    /**
     * @param  class-string<Person>  $model
     * @param  list<string>  $order
     * @param  null|callable(list<string>, list<string>): void  $afterReordering
     */
    public function handle(string $model, array $order, ?callable $afterReordering = null): void
    {
        if ($order === []) {
            return;
        }

        DB::transaction(function () use ($model, $order, $afterReordering): void {
            $before = $model::query()->featured()->ordered()->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (count(array_unique($order)) !== count($order) || array_diff($order, $before) !== []) {
                throw ValidationException::withMessages(['order' => 'Only distinct featured people may be reordered.']);
            }

            $selected = array_fill_keys($order, true);
            $offset = 0;
            $after = array_map(function (string $id) use ($order, $selected, &$offset): string {
                return isset($selected[$id]) ? $order[$offset++] : $id;
            }, $before);

            if ($before === $after) {
                return;
            }

            $model::setNewOrder($after);
            if ($afterReordering !== null) {
                $afterReordering($before, $after);
            }
        });
    }
}
