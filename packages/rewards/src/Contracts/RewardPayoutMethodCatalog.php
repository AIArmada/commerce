<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

/**
 * Payout method + fee-schedule boundary.
 *
 * Registered != supported: a method is supported only when its runtime
 * prerequisites hold (manual rails need operator handling; automated rails
 * need installed credentials). Fee math and destination-shape validation
 * always come from the installed method — core never reimplements a fee
 * schedule or invents credential keys.
 *
 * The default binding serves the configured `rewards.payouts.methods` with
 * zero fees and operator destinations.
 */
interface RewardPayoutMethodCatalog
{
    /**
     * @return list<string> registered method keys.
     */
    public function registered(): array;

    public function isRegistered(string $method): bool;

    public function isManual(string $method): bool;

    public function isSupported(string $method): bool;

    /**
     * @return array<string, array{registered: bool, manual: bool, supported: bool, missing_credentials: list<string>}>
     */
    public function describe(): array;

    /**
     * @return list<string> missing prerequisite keys; empty means configured.
     */
    public function missingCredentials(string $method): array;

    /**
     * Installed fee for the amount under the live schedule. Pure read.
     * Core solves program-paid gross-ups against this schedule via
     * `RewardPayoutTerms::grossForNet()`.
     */
    public function feeFor(string $method, int $amountMinor, string $currency): int;

    /**
     * Installed provider-destination details for one frozen account ref.
     * Operator rails carry no provider destination: the frozen snapshot
     * stays the operator's reconciliation authority.
     *
     * @param  array<string, mixed>  $audit  audit-only extras (payee id, display, operation key).
     * @return array<string, mixed>
     */
    public function detailsFor(string $method, string $accountRef, array $audit = []): array;

    /**
     * Installed destination handle inside stored method details, or null
     * for operator rails that carry none.
     *
     * @param  array<string, mixed>  $details
     */
    public function destinationRefFor(string $method, array $details): ?string;

    /**
     * Destination-shape errors from the installed method's own validation
     * contract. Empty means valid.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    public function validateDestination(string $method, array $details): array;
}
