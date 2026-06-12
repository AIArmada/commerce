<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface Shareable
{
    public function shareTitle(): string;

    public function shareUrl(): string;

    public function shareDescription(): ?string;

    public function shareImage(): ?string;
}
