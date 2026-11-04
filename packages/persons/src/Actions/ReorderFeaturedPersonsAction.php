<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

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

            $remaining = $order;
            $after = array_map(function (string $id) use ($order, &$remaining): string {
                return in_array($id, $order, true) ? array_shift($remaining) : $id;
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
