<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

use Illuminate\Support\HtmlString;

interface RichContentRendererInterface
{
    /**
     * @param  array<string, mixed>|null  $content
     * @param  array<string, mixed>  $mergeTags
     */
    public function render(?array $content, array $mergeTags = []): HtmlString;
}
