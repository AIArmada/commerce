<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Runtime\VoucherReservations;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

final class ManageVoucherReservations implements VoucherReservations
{
    use QueriesVouchers;

    public function reserve(string $code, string $sessionId): void
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        $cacheKey = $this->reservationCacheKey((string) $voucher->id, $sessionId);
        $sessionsKey = $this->reservationSessionsCacheKey((string) $voucher->id);
        $ttl = config('vouchers.reservation.ttl', 900); // 15 minutes default

        $this->guardSessionIndex($sessionsKey, function () use ($sessionsKey, $sessionId, $ttl, $cacheKey, $voucher): void {
            Cache::put($cacheKey, [
                'voucher_id' => $voucher->id,
                'session_id' => $sessionId,
                'reserved_at' => CarbonImmutable::now()->toIso8601String(),
            ], $ttl);
            $sessionIds = Cache::get($sessionsKey, []);

            if (! is_array($sessionIds)) {
                $sessionIds = [];
            }

            $sessionIds = array_values(array_filter(
                $sessionIds,
                fn (mixed $sid): bool => is_string($sid) && Cache::has($this->reservationCacheKey((string) $voucher->id, $sid)),
            ));

            if (! in_array($sessionId, $sessionIds, true)) {
                $sessionIds[] = $sessionId;
            }

            Cache::put($sessionsKey, $sessionIds, $ttl);
        });
    }

    /**
     * Release a voucher reservation.
     *
     * @param  string|null  $sessionId  When provided, only releases that session's reservation
     */
    public function release(string $code, ?string $sessionId = null): void
    {
        $voucher = $this->voucherQuery()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return;
        }

        $sessionsKey = $this->reservationSessionsCacheKey((string) $voucher->id);

        if ($sessionId !== null) {
            $ttl = config('vouchers.reservation.ttl', 900);

            $this->guardSessionIndex($sessionsKey, function () use ($sessionsKey, $sessionId, $ttl, $voucher): void {
                Cache::forget($this->reservationCacheKey((string) $voucher->id, $sessionId));
                $sessionIds = Cache::get($sessionsKey, []);

                if (! is_array($sessionIds)) {
                    return;
                }

                $sessionIds = array_values(array_filter(
                    $sessionIds,
                    fn (mixed $sid): bool => ! (is_string($sid) && $sid === $sessionId),
                ));

                if ($sessionIds === []) {
                    Cache::forget($sessionsKey);
                } else {
                    Cache::put($sessionsKey, $sessionIds, $ttl);
                }
            });

            return;
        }

        $this->guardSessionIndex($sessionsKey, function () use ($sessionsKey, $voucher): void {
            $sessionIds = Cache::get($sessionsKey, []);

            if (is_array($sessionIds)) {
                foreach ($sessionIds as $sid) {
                    if (is_string($sid) && $sid !== '') {
                        Cache::forget($this->reservationCacheKey((string) $voucher->id, $sid));
                    }
                }
            }

            Cache::forget($sessionsKey);
        });
    }

    /**
     * @param  Closure(): void  $callback
     */
    private function guardSessionIndex(string $sessionsKey, Closure $callback): void
    {
        $lock = Cache::lock($sessionsKey . ':lock', 10);

        $lock->block(5, $callback);
    }

    private function reservationCacheKey(string $voucherId, string $sessionId): string
    {
        return "voucher_reservation:{$voucherId}:{$sessionId}";
    }

    private function reservationSessionsCacheKey(string $voucherId): string
    {
        return "voucher_reservation_sessions:{$voucherId}";
    }
}
