<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Enums\RewardPayeeStatus;
use AIArmada\Rewards\Models\RewardPayee;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardPayoutTerms;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Register and verify a recipient payout destination.
 *
 * Registration stores an operator handle plus a masked display value; full
 * credentials stay with the operator/provider. Verification is an
 * independent staff act: the verifier must be a staff/admin user who is
 * not the recipient. Only methods the bound catalog serves can be
 * verified; an unconfigured method is refused. For served methods the
 * destination SHAPE is validated by the bound catalog's own
 * validateDetails contract, so a payee that can never be paid is refused
 * instead of stranded. A new registration for the same recipient + method
 * supersedes prior rows; frozen per-operation snapshots keep reconciling
 * against the original terms.
 */
final readonly class VerifyRewardPayeeAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function handle(
        string $recipientType,
        string $recipientId,
        string $method,
        string $accountRef,
        string $accountDisplay,
        string $verifierType,
        ?string $verifierId,
        string $verifierRole,
        ?array $evidence = null,
        ?CarbonInterface $decidedAt = null,
    ): RewardPayee {
        RewardLedgerEntries::assertActor($verifierType, $verifierId, $verifierRole);

        if ($verifierType !== 'user' || ! in_array($verifierRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Payee verification requires a staff/admin user identity.');
        }

        $recipientType = mb_trim($recipientType);
        $recipientId = mb_trim($recipientId);
        $method = mb_trim($method);
        $accountRef = mb_trim($accountRef);
        $accountDisplay = mb_trim($accountDisplay);

        if ($recipientType === '' || $recipientId === '') {
            throw new InvalidArgumentException('Recipient type and id must be non-empty.');
        }

        if ($method === '' || mb_strlen($method) > 32) {
            throw new InvalidArgumentException('Payee method must be 1..32 characters.');
        }

        if ($accountRef === '' || mb_strlen($accountRef) > 191) {
            throw new InvalidArgumentException('Payee account ref must be 1..191 characters.');
        }

        if ($accountDisplay === '' || mb_strlen($accountDisplay) > 191) {
            throw new InvalidArgumentException('Payee account display must be 1..191 characters.');
        }

        if ($verifierId === $recipientId) {
            throw new InvalidArgumentException('Payee self-verification is prohibited: the verifier must not be the recipient.');
        }

        $catalog = app(RewardPayoutMethodCatalog::class);

        if (! $catalog->isRegistered($method)) {
            throw new InvalidArgumentException("Payout method [{$method}] is not configured.");
        }

        RewardPayoutTerms::assertValidDestination(
            $method,
            RewardPayoutTerms::detailsFor($method, $accountRef)
        );

        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        return DB::transaction(function () use (
            $recipientType,
            $recipientId,
            $method,
            $accountRef,
            $accountDisplay,
            $verifierId,
            $evidence,
            $decided,
        ): RewardPayee {
            $prior = RewardPayee::query()
                ->where('recipient_type', $recipientType)
                ->where('recipient_id', $recipientId)
                ->where('method', $method)
                ->whereIn('status', [RewardPayeeStatus::Pending->value, RewardPayeeStatus::Verified->value])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($prior as $row) {
                $row->forceFill([
                    'status' => RewardPayeeStatus::Superseded,
                    'superseded_at' => $decided,
                ])->save();
            }

            return RewardPayee::query()->create([
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'method' => $method,
                'account_ref' => $accountRef,
                'account_display' => $accountDisplay,
                'status' => RewardPayeeStatus::Verified,
                'evidence' => $evidence,
                'verified_by' => $verifierId,
                'verified_at' => $decided,
            ]);
        });
    }
}
