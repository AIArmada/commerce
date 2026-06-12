<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

trait ReferencedByEvents
{
    public function eventReferenceTitle(): string
    {
        return $this->title ?? $this->name ?? '';
    }

    public function eventReferenceCitation(): ?string
    {
        return $this->citation ?? $this->description ?? null;
    }

    public function eventReferenceUrl(): ?string
    {
        return $this->url ?? $this->link ?? null;
    }
}
