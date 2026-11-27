<?php

declare(strict_types=1);

namespace AIArmada\Referrals\EntryPoints\Commands;

use AIArmada\Referrals\Actions\ExpireReferralInvites;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ExpireReferralInvitesCommand extends Command
{
    protected $signature = 'referrals:expire-invites';

    protected $description = 'Expire pending and accepted referral invites past their deadline.';

    public function handle(): int
    {
        $expiredCount = app(ExpireReferralInvites::class)->handle(CarbonImmutable::now());
        $this->info(sprintf('Expired %d referral invite(s).', $expiredCount));

        return self::SUCCESS;
    }
}
