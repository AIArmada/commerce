<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleCategory;
use RuntimeException;

final readonly class DeleteTitleCategory
{
    public function execute(TitleCategory $category): bool
    {
        return $category->getConnection()->transaction(function () use ($category): bool {
            $locked = TitleCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();

            $this->deleteTitles($locked);

            if (! $locked->delete()) {
                throw new RuntimeException('Title category deletion was cancelled.');
            }

            return true;
        });
    }

    public function deleteDependents(TitleCategory $category): void
    {
        $category->getConnection()->transaction(function () use ($category): void {
            $this->deleteTitles($category);
        });
    }

    private function deleteTitles(TitleCategory $category): void
    {
        $category->titles()->eachById(static function (Title $title): void {
            if (! $title->delete()) {
                throw new RuntimeException('Title category dependent deletion was cancelled.');
            }
        }, 500);
    }
}
