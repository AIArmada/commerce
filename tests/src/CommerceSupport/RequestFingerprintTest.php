<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\RequestFingerprint;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

function payloadDiffProbeUser(int | string | null $identifier): ?Authenticatable
{
    if ($identifier === null) {
        return null;
    }

    return new class($identifier) implements Authenticatable
    {
        public function __construct(private int | string $identifier) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int | string
        {
            return $this->identifier;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };
}

function payloadDiffProbeRequest(?Authenticatable $user, array $server = []): Request
{
    $request = Request::create('/', 'GET', server: $server);
    $request->setUserResolver(static fn (): ?Authenticatable => $user);

    return $request;
}

it('fingerprints authenticated users by identifier', function (): void {
    expect(RequestFingerprint::resolve(payloadDiffProbeRequest(payloadDiffProbeUser('user-1'))))
        ->toBe('user:user-1')
        ->and(RequestFingerprint::resolve(payloadDiffProbeRequest(payloadDiffProbeUser(42))))
        ->toBe('user:42');
});

it('fingerprints stringable identifiers as authenticated users', function (): void {
    $identifier = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'object-id-7';
        }
    };

    $user = new class($identifier) implements Authenticatable
    {
        public function __construct(private mixed $identifier) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->identifier;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };

    expect(RequestFingerprint::resolve(payloadDiffProbeRequest($user)))->toBe('user:object-id-7');
});

it('fingerprints guests by hashed ip and user agent', function (): void {
    $fingerprint = RequestFingerprint::resolve(
        payloadDiffProbeRequest(null, ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'TestAgent/1.0'])
    );

    expect($fingerprint)->toBe('guest:' . hash('sha256', '203.0.113.7|TestAgent/1.0'));
});

it('produces stable guest fingerprints and falls back on missing server data', function (): void {
    $server = ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'TestAgent/1.0'];
    $first = RequestFingerprint::resolve(payloadDiffProbeRequest(null, $server));
    $second = RequestFingerprint::resolve(payloadDiffProbeRequest(null, $server));
    $fallback = RequestFingerprint::resolve(
        payloadDiffProbeRequest(null, ['REMOTE_ADDR' => null, 'HTTP_USER_AGENT' => null])
    );

    expect($first)->toBe($second)
        ->and($fallback)->toBe('guest:' . hash('sha256', 'unknown-ip|unknown-agent'));
});
