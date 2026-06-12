<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

trait UsedAsEventMaterial
{
    public function eventMaterialTitle(): string
    {
        return $this->title ?? $this->name ?? '';
    }

    public function eventMaterialType(): string
    {
        return $this->material_type ?? $this->type ?? 'file';
    }

    public function eventMaterialUrl(): ?string
    {
        return $this->url ?? $this->path ?? null;
    }
}
