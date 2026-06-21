<?php

declare(strict_types=1);

use AIArmada\Communications\Contracts\ConsentResolver;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Contracts\PreferenceResolver;
use AIArmada\Communications\Contracts\QuietHoursResolver;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Contracts\SuppressionResolver;
use AIArmada\Communications\Data\RenderedContentData;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Models\CommunicationTemplate;
use Illuminate\Notifications\Messages\MailMessage;

beforeEach(function (): void {
    config()->set('communications.cache.idempotency_store', 'array');
});

test('null recipient snapshot resolver resolves identifiable object', function (): void {
    $resolver = app(RecipientSnapshotResolver::class);
    $notifiable = new class
    {
        public function getKey(): string
        {
            return 'test-123';
        }
    };

    $result = $resolver->resolve($notifiable);
    expect($result->identifier)->toBe('test-123');
});

test('destination resolver returns null for non-notifiable', function (): void {
    $resolver = app(DestinationResolver::class);
    $result = $resolver->resolve(null, 'mail');

    expect($result)->toBeNull();
});

test('destination resolver resolves and protects from notifiable', function (): void {
    $resolver = app(DestinationResolver::class);
    $notifiable = new class
    {
        public function routeNotificationForMail(): string
        {
            return 'user@example.com';
        }
    };

    $result = $resolver->resolve($notifiable, 'mail');

    expect($result)->not->toBeNull();
    expect($result->destination)->toBe('user@example.com');
    expect($result->ciphertext)->not->toBe('user@example.com');
    expect($result->hash)->not->toBeNull();
    expect($result->hint)->toStartWith('us');
});

test('destination resolver returns null when notifiable has no route', function (): void {
    $resolver = app(DestinationResolver::class);
    $notifiable = new class {};

    $result = $resolver->resolve($notifiable, 'mail');

    expect($result)->toBeNull();
});

test('null content renderer render method returns RenderedContentData', function (): void {
    $resolver = app(ContentRenderer::class);
    $template = CommunicationTemplate::create([
        'key' => 'test',
        'name' => 'Test',
        'category' => 'mail',
        'status' => TemplateStatus::Draft,
    ]);

    $result = $resolver->render($template, 'mail', 'en', []);

    expect($result)->toBeInstanceOf(RenderedContentData::class);
});

test('null content renderer renderFromNotification works with notifiable object', function (): void {
    $resolver = app(ContentRenderer::class);
    $notifiable = new class
    {
        public function routeNotificationForMail(): string
        {
            return 'test@example.com';
        }
    };

    $notification = new class
    {
        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Test');
        }
    };

    $result = $resolver->renderFromNotification($notifiable, $notification, 'mail');
    expect($result)->toBeInstanceOf(RenderedContentData::class);
});

test('null consent resolver returns consented for non-marketing', function (): void {
    $resolver = app(ConsentResolver::class);
    $result = $resolver->resolve(null, null, 'mail', 'transactional');

    expect($result->consented)->toBeTrue();
});

test('null consent resolver denies marketing', function (): void {
    $resolver = app(ConsentResolver::class);
    $result = $resolver->resolve(null, null, 'mail', 'marketing');

    expect($result->consented)->toBeFalse();
});

test('null suppression resolver returns not suppressed', function (): void {
    $resolver = app(SuppressionResolver::class);
    $result = $resolver->resolve(null, null, null, 'mail', 'transactional');

    expect($result->suppressed)->toBeFalse();
});

test('null preference resolver returns enabled', function (): void {
    $resolver = app(PreferenceResolver::class);
    $result = $resolver->isEnabled(null, null, 'mail', 'marketing');

    expect($result)->toBeTrue();
});

test('null preference resolver returns null for opted in', function (): void {
    $resolver = app(PreferenceResolver::class);
    $result = $resolver->isOptedIn(null, null, 'mail', 'marketing');

    expect($result)->toBeNull();
});

test('null quiet hours resolver returns no restriction', function (): void {
    $resolver = app(QuietHoursResolver::class);
    $result = $resolver->isInQuietHours(null, 'UTC');

    expect($result)->toBeFalse();
});

test('destination protector encrypts and decrypts', function (): void {
    $protector = app(DestinationProtector::class);

    $ciphertext = $protector->encrypt('test@example.com');
    expect($ciphertext)->not->toBe('test@example.com');

    $decrypted = $protector->decrypt($ciphertext);
    expect($decrypted)->toBe('test@example.com');
});

test('destination protector produces consistent hash', function (): void {
    $protector = app(DestinationProtector::class);

    $hash1 = $protector->hash('test@example.com');
    $hash2 = $protector->hash('test@example.com');
    $hash3 = $protector->hash('other@example.com');

    expect($hash1)->toBe($hash2);
    expect($hash1)->not->toBe($hash3);
});

test('destination protector creates hint', function (): void {
    $protector = app(DestinationProtector::class);
    $hint = $protector->hint('test@example.com');
    expect($hint)->toMatch('/^te\*+/');
    expect(mb_strlen($hint))->toBe(16);
});

test('idempotency lock acquires and checks', function (): void {
    $lock = app(IdempotencyLock::class);

    expect($lock->exists('test-key'))->toBeFalse();

    $lock->acquire('test-key', 60);

    expect($lock->exists('test-key'))->toBeTrue();
});

test('payload redactor redacts sensitive keys', function (): void {
    $redactor = app(PayloadRedactor::class);

    $payload = [
        'email' => 'user@example.com',
        'ssn' => '123-45-6789',
        'password' => 'secret123',
    ];

    $redacted = $redactor->redact($payload);

    expect($redacted['email'])->toBe('user@example.com');
    expect($redacted['ssn'])->toBe('**[REDACTED]**');
    expect($redacted['password'])->toBe('**[REDACTED]**');
});
