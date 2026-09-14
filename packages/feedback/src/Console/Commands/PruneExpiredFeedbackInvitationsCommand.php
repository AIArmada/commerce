<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class PruneExpiredFeedbackInvitationsCommand extends Command
{
    protected $signature = 'feedback:prune-expired-invitations
        {--dry-run : Show how many invitations would be marked expired without making changes}';

    protected $description = 'Mark past-due feedback invitations as expired';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $runner = new OwnerBatchRunner(
            FeedbackInvitation::class,
            ['enabled' => 'feedback.owner.enabled', 'include_global' => 'feedback.owner.include_global'],
        );

        $expired = $runner->forEach(fn (): int => $this->expireDueInvitations($dryRun))->sum();

        if ($dryRun) {
            $this->warn("Dry run: {$expired} invitation(s) would be marked expired.");
        } else {
            $this->info("Marked {$expired} invitation(s) expired.");
        }

        return self::SUCCESS;
    }

    private function expireDueInvitations(bool $dryRun): int
    {
        $query = FeedbackInvitation::query()
            ->whereNotIn('status', [
                FeedbackInvitationStatus::Expired,
                FeedbackInvitationStatus::Cancelled,
                FeedbackInvitationStatus::Submitted,
            ])
            ->where('expires_at', '<', CarbonImmutable::now());

        if ($dryRun) {
            return $query->count();
        }

        $expired = 0;

        $query->chunkById(500, function ($invitations) use (&$expired): void {
            foreach ($invitations as $invitation) {
                $invitation->forceFill(['status' => FeedbackInvitationStatus::Expired])->save();
                $expired++;
            }
        });

        return $expired;
    }
}
