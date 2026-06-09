<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

final class BulkPayoutAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Process Payouts');
        $this->icon('heroicon-o-banknotes');
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading('Process Selected Payouts');
        $this->modalDescription('Are you sure you want to process these payouts? This will initiate payment transfers.');

        $this->action(function (Collection $records): void {
            $processed = 0;
            $failed = 0;

            foreach ($records as $record) {
                if (! $record instanceof AffiliatePayout) {
                    continue;
                }

                Gate::authorize('update', $record);

                $payout = (bool) config('affiliates.owner.enabled', false)
                    ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
                    : AffiliatePayout::findOrFail($record->getKey());

                if (! $payout->status->equals(PendingPayout::class)) {
                    continue;
                }

                $result = app(ProcessAffiliatePayout::class)->handle($payout);

                if ($result->success) {
                    $processed++;

                    continue;
                }

                $failed++;
            }

            if ($processed > 0) {
                $this->success();
            }

            $this->sendSuccessNotification();
        });

        $this->deselectRecordsAfterCompletion();
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_process_payouts';
    }
}
