<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface Bookmarkable
{
    public function bookmarkTitle(): string;

    public function bookmarkUrl(): ?string;

    public function bookmarkImage(): ?string;
}
