<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface InboxCommunicationCreator
{
    public function create(): string;
}
