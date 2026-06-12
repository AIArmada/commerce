<?php
declare(strict_types=1);
namespace AIArmada\Engagement\Contracts;

interface ShareUrlGenerator
{
    public function generateShareUrl(mixed $shareable, array $options = []): string;
}
