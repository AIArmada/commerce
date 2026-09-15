<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Concerns;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Audit related-state edits from Livewire pages.
 *
 * Capture a snapshot before the edit and audit the differences after it;
 * records that are not auditable, or snapshots without changes, record
 * nothing. The snapshot shape is page-defined (related rows flattened to
 * scalar state), keeping relation traversal out of the audit layer.
 */
trait AuditsRelatedStateChanges
{
    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $relatedAuditSnapshot = [];

    /**
     * @return array<string, mixed>
     */
    abstract protected function getRelatedAuditSnapshot(Model $record): array;

    protected function captureRelatedAuditSnapshot(Model $record): void
    {
        $this->relatedAuditSnapshot = $this->getRelatedAuditSnapshot($record);
    }

    protected function auditRelatedStateChanges(Model $record, string $event): void
    {
        if (! $record instanceof AuditableContract || ! method_exists($record, 'recordCustomAuditDifferences')) {
            return;
        }

        /** @var Model&object{recordCustomAuditDifferences(string, array<string, mixed>, array<string, mixed>): void} $auditable */
        $auditable = $record;

        $auditable->recordCustomAuditDifferences($event, $this->relatedAuditSnapshot, $this->getRelatedAuditSnapshot($record));
    }
}
