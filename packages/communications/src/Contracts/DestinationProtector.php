<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface DestinationProtector
{
    public function encrypt(string $destination): string;

    public function decrypt(string $ciphertext): string;

    public function hash(string $destination): string;

    public function hint(string $destination): string;
}
