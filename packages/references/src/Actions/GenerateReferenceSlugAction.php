<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Models\Reference;
use Illuminate\Support\Str;

final class GenerateReferenceSlugAction
{
    public function execute(Reference $reference, ?string $source = null): string
    {
        $field = $source ?? 'title';

        $slug = Str::slug((string) $reference->getAttribute($field));

        $baseSlug = $slug;
        $suffix = 1;

        while ($this->slugExists($slug, $reference)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, Reference $reference): bool
    {
        $query = Reference::where('slug', $slug);

        if ($reference->exists) {
            $query->where('id', '!=', $reference->id);
        }

        return $query->exists();
    }
}
