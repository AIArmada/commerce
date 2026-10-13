<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\AffiliateCashPosting\Support;

use AIArmada\Affiliates\Models\AffiliateBalance;
use RuntimeException;

/**
 * Explicitly labeled TEST-ONLY award failure injector.
 *
 * Two honest modes, both inert until armed and both reset by the harness
 * setUp/tearDown. Never used outside this cash-post suite.
 *
 * Pre-write mode (`armOnce`): throws from the balance `creating` hook
 * BEFORE any balance insert or update. It proves a pre-write refusal
 * leaves zero effects and the retry commits exactly once. It is NOT a
 * mid-accounting money-rollback proof: no money has moved when it fires.
 *
 * Post-mutation mode (`armAfterFirstBalanceWrite`): throws from the
 * balance `created`/`updated` hooks AFTER the first real balance write
 * (the insert for a fresh balance, or the first increment UPDATE for a
 * pre-existing one). Eloquent fires those hooks only after the write
 * query succeeds, so the throw proves the conversion insert plus the
 * balance mutation roll back together through the real transaction.
 */
final class CashPostingFailureInjector
{
    public static bool $throwOnce = false;

    public static bool $throwAfterFirstBalanceWrite = false;

    public static int $balanceWritesObserved = 0;

    public static function armOnce(): void
    {
        self::$throwOnce = true;

        AffiliateBalance::creating(static function (): void {
            if (self::$throwOnce) {
                self::$throwOnce = false;

                throw new RuntimeException('cashpost-injected-pre-write-failure');
            }
        });
    }

    public static function armAfterFirstBalanceWrite(): void
    {
        self::$throwAfterFirstBalanceWrite = true;
        self::$balanceWritesObserved = 0;

        $afterWrite = static function (): void {
            if (! self::$throwAfterFirstBalanceWrite) {
                return;
            }

            self::$balanceWritesObserved++;
            self::$throwAfterFirstBalanceWrite = false;

            throw new RuntimeException('cashpost-injected-post-mutation-failure');
        };

        AffiliateBalance::created($afterWrite);
        AffiliateBalance::updated($afterWrite);
    }

    public static function disarm(): void
    {
        self::$throwOnce = false;
        self::$throwAfterFirstBalanceWrite = false;
        self::$balanceWritesObserved = 0;
    }
}
