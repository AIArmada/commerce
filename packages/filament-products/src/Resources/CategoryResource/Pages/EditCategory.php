<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\Products\Actions\DeleteCatalogRecord;
use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\Products\Actions\SaveCategory;
use AIArmada\Products\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()->using(fn (Category $record): bool => app(DeleteCatalogRecord::class)->execute($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Category
    {
        abort_unless($record instanceof Category, 404);

        try {
            return app(SaveCategory::class)->update($record, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parent_id' => $exception->getMessage()]);
        }
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

        return $data;
    }
}
