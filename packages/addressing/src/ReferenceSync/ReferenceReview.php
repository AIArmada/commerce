<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReferenceReview
{
    public function decide(int $id, string $decision, string $actor, ?string $notes = null): void
    {
        if (! in_array($decision, ['approved', 'rejected', 'pending'], true)) {
            throw new \InvalidArgumentException('Unsupported review decision.');
        }
        DB::transaction(static function () use ($id, $decision, $actor, $notes): void {
            $change = DB::table(DiffEngine::TABLE)->where('id', $id)->lockForUpdate()->first();
            if ($change === null) {
                throw new RuntimeException('Reference change no longer exists. Refresh the comparison.');
            }
            if ($decision === 'approved' && $change->operation === 'conflict') {
                throw new RuntimeException('A three-way conflict must be resolved before approval.');
            }
            DB::table(DiffEngine::TABLE)->where('id', $id)->update([
                'decision' => $decision,
                'notes' => $notes,
                'reviewed_by' => $actor,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
