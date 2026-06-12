<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface CanRegisterForEvents
{
    public function eventRegistrantName(): string;

    public function eventRegistrantEmail(): ?string;

    public function eventRegistrantPhone(): ?string;
}
