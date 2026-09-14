<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

final class EditAffiliateOfferCategory extends EditRecord
{
    protected static string $resource = AffiliateOfferCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $parentId = $data['parent_id'] ?? null;

        if (is_scalar($parentId) && (string) $parentId !== '') {
            /** @var AffiliateOfferCategory $parent */
            $parent = OwnerContext::withOwner(null, fn (): AffiliateOfferCategory => AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->whereKey((string) $parentId)
                ->firstOrFail());

            $data['parent_id'] = (string) $parent->getKey();

            self::assertParentIsNotSelfOrDescendant((string) $this->record->getKey(), $data['parent_id']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Reject parent assignments that would create a hierarchy cycle: the
     * parent must be neither the record itself nor one of its descendants.
     * Walks the candidate parent chain upward (depth-capped) in explicit
     * global context so cross-tenant parents stay visible to the admin form.
     *
     * @throws ValidationException
     */
    private static function assertParentIsNotSelfOrDescendant(string $recordId, string $parentId): void
    {
        $seen = [$recordId];
        $currentId = $parentId;
        $depth = 0;

        while ($currentId !== null && $depth < 100) {
            if ($currentId === $recordId) {
                throw ValidationException::withMessages([
                    'data.parent_id' => 'A category cannot be assigned to itself or one of its descendants.',
                ]);
            }

            if (in_array($currentId, $seen, true)) {
                break;
            }

            $seen[] = $currentId;

            $lookupId = $currentId;

            /** @var string|null $currentId */
            $currentId = OwnerContext::withOwner(null, fn (): ?string => AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->whereKey($lookupId)
                ->value('parent_id'));

            $depth++;
        }
    }
}
