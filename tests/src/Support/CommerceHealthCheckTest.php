<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;

it('returns commerce health check results from concrete checks', function (): void {
    $check = SupportHealthCheck::returning(Result::make()->ok('All systems go'));

    $result = $check->run();

    expect($result->status->equals(Status::ok()))->toBeTrue()
        ->and($result->getNotificationMessage())->toBe('All systems go');
});

it('converts commerce health check exceptions to failed results', function (): void {
    $check = SupportHealthCheck::throwing(new RuntimeException('Gateway timeout'));

    $result = $check->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->getNotificationMessage())->toContain('Gateway timeout');
});

it('builds success failure and warning health results with metadata', function (): void {
    $check = new SupportHealthCheck;

    $success = $check->publicSuccess('OK', ['gateway' => 'chip']);
    $failure = $check->publicFailure('Down', ['gateway' => 'chip']);
    $warning = $check->publicWarning('Slow', ['latency' => 250]);

    expect($success->status->equals(Status::ok()))->toBeTrue()
        ->and($success->meta)->toBe(['gateway' => 'chip'])
        ->and($failure->status->equals(Status::failed()))->toBeTrue()
        ->and($failure->meta)->toBe(['gateway' => 'chip'])
        ->and($warning->status->equals(Status::warning()))->toBeTrue()
        ->and($warning->meta)->toBe(['latency' => 250]);
});

final class SupportHealthCheck extends CommerceHealthCheck
{
    private static ?Result $nextResult = null;

    private static ?Throwable $nextException = null;

    public static function returning(Result $result): self
    {
        self::$nextResult = $result;
        self::$nextException = null;

        return new self;
    }

    public static function throwing(Throwable $throwable): self
    {
        self::$nextResult = null;
        self::$nextException = $throwable;

        return new self;
    }

    public function publicSuccess(string $message, array $meta): Result
    {
        return $this->success($message, $meta);
    }

    public function publicFailure(string $message, array $meta): Result
    {
        return $this->failure($message, $meta);
    }

    public function publicWarning(string $message, array $meta): Result
    {
        return $this->warning($message, $meta);
    }

    protected function performCheck(): Result
    {
        if (self::$nextException !== null) {
            throw self::$nextException;
        }

        return self::$nextResult ?? Result::make()->ok('OK');
    }
}
