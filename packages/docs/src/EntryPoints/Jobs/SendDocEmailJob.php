<?php

declare(strict_types=1);

namespace AIArmada\Docs\EntryPoints\Jobs;

use AIArmada\Docs\Actions\DeliverDocEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendDocEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $docEmailId,
        public string $docId,
        public bool $attachPdf = true,
    ) {
        $this->queue = (string) config('docs.email.queue', 'default');
    }

    public function handle(DeliverDocEmail $deliverDocEmail): void
    {
        $deliverDocEmail->execute($this->docEmailId, $this->docId, $this->attachPdf);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['docs', 'email', "doc-email:{$this->docEmailId}"];
    }
}
