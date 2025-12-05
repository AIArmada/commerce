<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Jobs;

use AIArmada\FilamentPermissions\Models\PermissionAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteAuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data
    ) {
        $this->onQueue(config('filament-permissions.audit.queue', 'default'));
    }

    public function handle(): void
    {
        PermissionAuditLog::create($this->data);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function tries(): int
    {
        return 3;
    }
}
