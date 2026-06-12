<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface HasEventCoordinates
{
    public function eventLatitude(): ?float;

    public function eventLongitude(): ?float;

    public function eventGeoPoint(): mixed;
}
