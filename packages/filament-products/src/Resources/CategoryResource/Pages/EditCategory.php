<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\Products\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['parent_id']) && is_string($data['parent_id'])) {
            $allowed = OwnerScopedIds::allowedIds(Category::class, [$data['parent_id']]);

            if ($allowed === []) {
                unset($data['parent_id']);
            } else {
                $data['parent_id'] = $allowed[0];
            }
        }

        if (isset($data['parent_id']) && is_string($data['parent_id']) && isset($this->record) && $this->record instanceof Model) {
            $this->assertParentDoesNotCycle($data['parent_id'], (string) $this->record->getKey());
        }

        return $data;
    }

    private function assertParentDoesNotCycle(string $parentId, string $recordId): void
    {
        if ($parentId === $recordId) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be its own parent.'],
            ]);
        }

        $visited = [$recordId];
        $currentId = $parentId;

        while ($currentId !== '') {
            if (in_array($currentId, $visited, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['This parent would create a cycle in the category hierarchy.'],
                ]);
            }

            $visited[] = $currentId;

            $parentOfCurrent = Category::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->whereKey($currentId)
                ->value('parent_id');

            if (! is_string($parentOfCurrent) && ! is_int($parentOfCurrent)) {
                return;
            }

            $currentId = (string) $parentOfCurrent;
        }
    }
}
