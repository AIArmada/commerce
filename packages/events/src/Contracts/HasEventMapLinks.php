<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface HasEventMapLinks
{
    public function eventGoogleMapsUrl(): ?string;

    public function eventWazeUrl(): ?string;

    public function eventMapUrl(): ?string;
}
