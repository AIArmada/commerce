<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventChangeNoticeWorkflow
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function publishNotice(string $changeLogId, array $options = []): void;

    public function retractNotice(string $changeLogId): void;
}
