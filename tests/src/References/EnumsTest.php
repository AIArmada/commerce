<?php

declare(strict_types=1);

use AIArmada\References\Enums\ReferencePartType;
use AIArmada\References\Enums\ReferenceStatus;
use AIArmada\References\Enums\ReferenceType;

describe('ReferenceType', function (): void {
    it('has all required cases', function (): void {
        expect(ReferenceType::Book->value)->toBe('book');
        expect(ReferenceType::Article->value)->toBe('article');
        expect(ReferenceType::Thesis->value)->toBe('thesis');
        expect(ReferenceType::Fatwa->value)->toBe('fatwa');
        expect(ReferenceType::Video->value)->toBe('video');
        expect(ReferenceType::Audio->value)->toBe('audio');
        expect(ReferenceType::Website->value)->toBe('website');
        expect(ReferenceType::Other->value)->toBe('other');
    });

    it('provides non-empty labels for all cases', function (): void {
        foreach (ReferenceType::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('ReferenceStatus', function (): void {
    it('has all required cases', function (): void {
        expect(ReferenceStatus::Draft->value)->toBe('draft');
        expect(ReferenceStatus::Published->value)->toBe('published');
        expect(ReferenceStatus::Archived->value)->toBe('archived');
    });

    it('provides non-empty labels for all cases', function (): void {
        foreach (ReferenceStatus::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('ReferencePartType', function (): void {
    it('has all required cases', function (): void {
        expect(ReferencePartType::Jilid->value)->toBe('jilid');
        expect(ReferencePartType::Juz->value)->toBe('juz');
        expect(ReferencePartType::Surah->value)->toBe('surah');
        expect(ReferencePartType::Chapter->value)->toBe('chapter');
        expect(ReferencePartType::Section->value)->toBe('section');
        expect(ReferencePartType::Page->value)->toBe('page');
    });

    it('provides non-empty labels for all cases', function (): void {
        foreach (ReferencePartType::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

test('all reference enums are string-backed', function (): void {
    $enums = [
        ReferenceType::class,
        ReferenceStatus::class,
        ReferencePartType::class,
    ];

    foreach ($enums as $enum) {
        $cases = $enum::cases();
        expect($cases)->not->toBeEmpty();
        foreach ($cases as $case) {
            expect($case->value)->toBeString();
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    }
});
