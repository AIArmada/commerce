<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\LabelData;

describe('LabelData', function (): void {
    it('can create label data with required fields', function (): void {
        $label = new LabelData(
            format: 'pdf',
            url: 'https://example.com/label.pdf',
            content: base64_encode('fake pdf content'),
            size: 'a4',
            trackingNumber: 'TRACK123'
        );

        expect($label->format)->toBe('pdf');
        expect($label->url)->toBe('https://example.com/label.pdf');
        expect($label->content)->toBe(base64_encode('fake pdf content'));
        expect($label->size)->toBe('a4');
        expect($label->trackingNumber)->toBe('TRACK123');
    });

    it('can create label data with minimal fields', function (): void {
        $label = new LabelData(format: 'zpl');

        expect($label->format)->toBe('zpl');
        expect($label->url)->toBeNull();
        expect($label->content)->toBeNull();
        expect($label->size)->toBeNull();
        expect($label->trackingNumber)->toBeNull();
    });

    it('checks if label has url', function (): void {
        $withUrl = new LabelData(format: 'pdf', url: 'https://example.com/label.pdf');
        $withoutUrl = new LabelData(format: 'zpl');

        expect($withUrl->hasUrl())->toBeTrue();
        expect($withoutUrl->hasUrl())->toBeFalse();
    });

    it('checks if label has content', function (): void {
        $withContent = new LabelData(format: 'zpl', content: 'ZPL content');
        $withoutContent = new LabelData(format: 'pdf', url: 'https://example.com/label.pdf');

        expect($withContent->hasContent())->toBeTrue();
        expect($withoutContent->hasContent())->toBeFalse();
    });

    it('decodes base64 content', function (): void {
        $originalContent = 'This is the decoded content';
        $encodedContent = base64_encode($originalContent);

        $label = new LabelData(format: 'zpl', content: $encodedContent);

        expect($label->getDecodedContent())->toBe($originalContent);
    });

    it('returns null when decoding without content', function (): void {
        $label = new LabelData(format: 'pdf', url: 'https://example.com/label.pdf');

        expect($label->getDecodedContent())->toBeNull();
    });
});
