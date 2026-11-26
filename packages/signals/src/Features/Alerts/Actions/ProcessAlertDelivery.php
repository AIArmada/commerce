<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Alerts\Actions;

use AIArmada\Signals\Contracts\AlertTransport;
use AIArmada\Signals\Data\AlertDeliveryMessage;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertDelivery;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class ProcessAlertDelivery
{
    public function __construct(
        private AlertTransport $transport,
        private SignalAlertDispatcher $dispatcher,
    ) {}

    public function run(string $deliveryId): void
    {
        $delivery = $this->claim($deliveryId);

        if (! $delivery instanceof SignalAlertDelivery) {
            return;
        }

        $claimedAttempt = $delivery->attempt_count;

        try {
            $status = $this->deliver($delivery);
            $this->finalizeAttempt($deliveryId, $claimedAttempt, $status, null);
        } catch (Throwable $exception) {
            $this->finalizeAttempt($deliveryId, $claimedAttempt, null, $exception);

            throw new RuntimeException('Signals alert delivery failed.', previous: $exception);
        }
    }

    public function fail(string $deliveryId, Throwable $exception): void
    {
        DB::transaction(function () use ($deliveryId, $exception): void {
            $delivery = SignalAlertDelivery::query()->lockForUpdate()->find($deliveryId);

            if (! $delivery instanceof SignalAlertDelivery || in_array($delivery->status, ['sent', 'dead'], true)) {
                return;
            }

            $delivery->forceFill([
                'status' => 'dead',
                'dead_at' => CarbonImmutable::now(),
                'leased_at' => null,
                'last_error_code' => $this->safeErrorCode($exception),
            ])->save();
            $this->refreshLog($delivery);
        });
    }

    /**
     * Finalize a transport outcome under a fresh row lock.
     *
     * The network send runs outside any transaction; only this narrow
     * reread-and-write is locked. Terminal sent/dead rows win, and a stale
     * completion never regresses a newer claim (identified by attempt_count).
     */
    private function finalizeAttempt(string $deliveryId, int $claimedAttempt, ?int $status, ?Throwable $error): void
    {
        DB::transaction(function () use ($deliveryId, $claimedAttempt, $status, $error): void {
            $delivery = SignalAlertDelivery::query()->lockForUpdate()->find($deliveryId);

            if (! $delivery instanceof SignalAlertDelivery) {
                return;
            }

            if (in_array($delivery->status, ['sent', 'dead'], true)) {
                return;
            }

            if ($delivery->attempt_count !== $claimedAttempt) {
                return;
            }

            if ($error instanceof Throwable) {
                $delivery->forceFill([
                    'status' => 'failed',
                    'leased_at' => null,
                    'last_error_code' => $this->safeErrorCode($error),
                ])->save();
            } else {
                $delivery->forceFill([
                    'status' => 'sent',
                    'sent_at' => $delivery->sent_at ?? CarbonImmutable::now(),
                    'leased_at' => null,
                    'response_status' => $status,
                    'last_error_code' => null,
                ])->save();
            }

            $this->refreshLog($delivery);
        });
    }

    private function claim(string $deliveryId): ?SignalAlertDelivery
    {
        return DB::transaction(function () use ($deliveryId): ?SignalAlertDelivery {
            $delivery = SignalAlertDelivery::query()->lockForUpdate()->find($deliveryId);

            if (! $delivery instanceof SignalAlertDelivery || in_array($delivery->status, ['sent', 'dead'], true)) {
                return null;
            }

            $leaseSeconds = max(30, (int) config('signals.features.alerts.delivery.lease_seconds', 120));

            if ($delivery->status === 'processing' && $delivery->leased_at?->isAfter(CarbonImmutable::now()->subSeconds($leaseSeconds))) {
                return null;
            }

            if ($delivery->attempt_count >= $delivery->max_attempts) {
                $delivery->forceFill(['status' => 'dead', 'dead_at' => CarbonImmutable::now(), 'leased_at' => null])->save();
                $this->refreshLog($delivery);

                return null;
            }

            $delivery->forceFill([
                'status' => 'processing',
                'attempt_count' => $delivery->attempt_count + 1,
                'leased_at' => CarbonImmutable::now(),
                'last_attempt_at' => CarbonImmutable::now(),
            ])->save();

            return $delivery;
        });
    }

    private function deliver(SignalAlertDelivery $delivery): ?int
    {
        $log = $delivery->alertLog()->firstOrFail();

        return $this->transport->send($delivery->channel, $delivery->destination ?? [], new AlertDeliveryMessage(
            ruleId: $log->signal_alert_rule_id,
            logId: $log->id,
            title: $log->title,
            severity: $log->severity,
            metricKey: $log->metric_key,
            message: $log->message,
            context: $log->context,
        ));
    }

    private function safeErrorCode(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            preg_match('/\bhttp_[1-5][0-9]{2}\b/', $message, $matches) === 1 => $matches[0],
            str_contains($message, 'invalid_destination') => 'invalid_destination',
            str_contains($message, 'unsupported_channel') => 'unsupported_channel',
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'timeout',
            str_contains($message, 'resolve'), str_contains($message, 'public') => 'unsafe_destination',
            default => 'transport_error',
        };
    }

    private function refreshLog(SignalAlertDelivery $delivery): void
    {
        $log = SignalAlertLog::query()->find($delivery->signal_alert_log_id);

        if ($log instanceof SignalAlertLog) {
            $this->dispatcher->refreshLogSummary($log);
        }
    }
}
