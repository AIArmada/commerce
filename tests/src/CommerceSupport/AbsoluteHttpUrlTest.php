<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\AbsoluteHttpUrl;

it('accepts only absolute http(s) urls with a host and no credentials', function (): void {
    expect(AbsoluteHttpUrl::normalize('https://example.com/label.pdf'))->toBe('https://example.com/label.pdf')
        ->and(AbsoluteHttpUrl::normalize('http://example.com:8080/label.pdf'))->toBe('http://example.com:8080/label.pdf')
        ->and(AbsoluteHttpUrl::normalize('https://user:pass@example.com/label.pdf'))->toBeNull()
        ->and(AbsoluteHttpUrl::normalize('https:///label.pdf'))->toBeNull()
        ->and(AbsoluteHttpUrl::normalize('javascript:alert(1)'))->toBeNull()
        ->and(AbsoluteHttpUrl::normalize('//example.com/label.pdf'))->toBeNull();
});
