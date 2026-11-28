<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherReservations;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

final class ManageVoucherReservations implements VoucherReservations
{
    public function __construct(private readonly VoucherCatalog $catalog) {}

    public function reserve(string $code, string $sessionId, ?string $voucherId = null): void
    {
        $id = $this->catalog->resolveId($code, $voucherId);

        if ($id === null) {
            throw VoucherNotFoundException::withCode($code);
        }

        $cacheKey = $this->reservationCacheKey($id, $sessionId);
        $sessionsKey = $this->reservationSessionsCacheKey($id);
        $ttl = config('vouchers.reservation.ttl', 900); // 15 minutes default

        $this->guardSessionIndex($sessionsKey, function () use ($sessionsKey, $sessionId, $ttl, $cacheKey, $id): void {
            Cache::put($cacheKey, [
                'voucher_id' => $id,
                'session_id' => $sessionId,
                'reserved_at' => CarbonImmutable::now()->toIso8601String(),
            ], $ttl);
            $sessionIds = Cache::get($sessionsKey, []);

            if (! is_array($sessionIds)) {
                $sessionIds = [];
            }

            $sessionIds = array_values(array_filter(
                $sessionIds,
                fn (mixed $sid): bool => is_string($sid) && Cache::has($this->reservationCacheKey($id, $sid)),
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
    public function release(string $code, ?string $sessionId = null, ?string $voucherId = null): void
    {
        $id = $this->catalog->resolveId($code, $voucherId);

        if ($id === null) {
            return;
        }

        $sessionsKey = $this->reservationSessionsCacheKey($id);

        if ($sessionId !== null) {
            $ttl = config('vouchers.reservation.ttl', 900);

            $this->guardSessionIndex($sessionsKey, function () use ($sessionsKey, $sessionId, $ttl, $id): void {
                Cache::forget($this->reservationCacheKey($id, $sessionId));
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

        $this->guardSessionIndex($sessionsKey, function () use ($sessionsKey, $id): void {
            $sessionIds = Cache::get($sessionsKey, []);

            if (is_array($sessionIds)) {
                foreach ($sessionIds as $sid) {
                    if (is_string($sid) && $sid !== '') {
                        Cache::forget($this->reservationCacheKey($id, $sid));
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
