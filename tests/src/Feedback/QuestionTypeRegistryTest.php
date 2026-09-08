<?php

declare(strict_types=1);

use AIArmada\Feedback\Support\QuestionTypeRegistry;

it('derives disabled question types without a static cache', function (): void {
    $registry = new ReflectionClass(QuestionTypeRegistry::class);

    expect($registry->hasProperty('disabledTypes'))->toBeFalse()
        ->and(QuestionTypeRegistry::disabledTypes())->toBe(['file_upload', 'signature'])
        ->and(QuestionTypeRegistry::availableTypes())->not->toHaveKeys(['file_upload', 'signature']);
});
