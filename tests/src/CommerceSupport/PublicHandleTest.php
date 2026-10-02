<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\PublicHandle;
use Illuminate\Support\Facades\Validator;

test('public handles share normalization and validation rules', function (): void {
    expect(PublicHandle::normalize(' SaifReviews '))->toBe('saifreviews')
        ->and(Validator::make(['handle' => 'saif_reviews'], ['handle' => PublicHandle::rules()])->passes())->toBeTrue();
    foreach (['admin', 'saif/reviews', '_saif', 'saif--reviews', str_repeat('a', 41)] as $handle) {
        expect(Validator::make(['handle' => $handle], ['handle' => PublicHandle::rules()])->fails())->toBeTrue();
    }
});

test('assigned handles are valid and identity assignment is deterministic', function (): void {
    foreach (['Saif Reviews', '', str_repeat('a', 80)] as $name) {
        expect(Validator::make(['handle' => PublicHandle::generate($name)], ['handle' => PublicHandle::rules()])->passes())->toBeTrue();
    }
    expect(PublicHandle::forIdentity('user:1'))->toBe(PublicHandle::forIdentity('user:1'))
        ->and(PublicHandle::forIdentity('user:2'))->not->toBe(PublicHandle::forIdentity('user:1'));
});
