<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\PrintWaybillData;

describe('PrintWaybillData', function (): void {
    it('creates from API array with base64 content (single parcel)', function (): void {
        $data = [
            'txlogisticId' => 'ORDER-123',
            'billCode' => 'JT987654321',
            'base64EncodeContent' => base64_encode('PDF content here'),
        ];

        $result = PrintWaybillData::fromApiArray($data);

        expect($result)
            ->toBeInstanceOf(PrintWaybillData::class)
            ->orderId->toBe('ORDER-123')
            ->trackingNumber->toBe('JT987654321')
            ->base64Content->toBe($data['base64EncodeContent'])
            ->urlContent->toBeNull()
            ->isMultiParcel->toBeFalse();
    });

    it('creates from API array with URL content (multi parcel)', function (): void {
        $data = [
            'txlogisticId' => 'ORDER-456',
            'billCode' => 'JT123456789',
            'urlContent' => 'https://api.jnt.com/download/waybill.pdf',
        ];

        $result = PrintWaybillData::fromApiArray($data);

        expect($result)
            ->toBeInstanceOf(PrintWaybillData::class)
            ->orderId->toBe('ORDER-456')
            ->urlContent->toBe('https://api.jnt.com/download/waybill.pdf')
            ->base64Content->toBeNull()
            ->isMultiParcel->toBeTrue();
    });

    it('handles both base64 and URL content', function (): void {
        $data = [
            'txlogisticId' => 'ORDER-789',
            'base64EncodeContent' => base64_encode('PDF'),
            'urlContent' => 'https://example.com/pdf',
        ];

        $result = PrintWaybillData::fromApiArray($data);

        expect($result)
            ->base64Content->not->toBeNull()
            ->urlContent->not->toBeNull()
            ->isMultiParcel->toBeFalse(); // Base64 takes precedence
    });

    it('accepts clean field names (orderId, trackingNumber)', function (): void {
        $data = [
            'orderId' => 'ORDER-CLEAN',
            'trackingNumber' => 'JT-CLEAN',
        ];

        $result = PrintWaybillData::fromApiArray($data);

        expect($result)
            ->orderId->toBe('ORDER-CLEAN')
            ->trackingNumber->toBe('JT-CLEAN');
    });

    it('includes template name', function (): void {
        $data = [
            'txlogisticId' => 'ORDER-123',
            'templateName' => 'custom_template',
        ];

        $result = PrintWaybillData::fromApiArray($data);

        expect($result->templateName)->toBe('custom_template');
    });

    it('converts to array', function (): void {
        $original = [
            'orderId' => 'ORDER-123',
            'trackingNumber' => 'JT123',
            'base64Content' => 'base64data',
            'urlContent' => null,
            'isMultiParcel' => false,
            'templateName' => 'default',
        ];

        $data = new PrintWaybillData(...$original);
        $array = $data->toArray();

        expect($array)->toBe($original);
    });

    it('detects base64 content availability', function (): void {
        $withBase64 = new PrintWaybillData(
            orderId: 'ORDER-1',
            trackingNumber: null,
            base64Content: 'content',
            urlContent: null,
            isMultiParcel: false
        );

        $withoutBase64 = new PrintWaybillData(
            orderId: 'ORDER-2',
            trackingNumber: null,
            base64Content: null,
            urlContent: 'url',
            isMultiParcel: true
        );

        expect($withBase64->hasBase64Content())->toBeTrue()
            ->and($withoutBase64->hasBase64Content())->toBeFalse();
    });

    it('detects URL content availability', function (): void {
        $withUrl = new PrintWaybillData(
            orderId: 'ORDER-1',
            trackingNumber: null,
            base64Content: null,
            urlContent: 'https://example.com',
            isMultiParcel: true
        );

        $withoutUrl = new PrintWaybillData(
            orderId: 'ORDER-2',
            trackingNumber: null,
            base64Content: 'content',
            urlContent: null,
            isMultiParcel: false
        );

        expect($withUrl->hasUrlContent())->toBeTrue()
            ->and($withoutUrl->hasUrlContent())->toBeFalse();
    });

    it('decodes PDF content', function (): void {
        $pdfContent = 'PDF binary content';
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: base64_encode($pdfContent),
            urlContent: null,
            isMultiParcel: false
        );

        expect($data->getPdfContent())->toBe($pdfContent);
    });

    it('returns null for PDF content when not available', function (): void {
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: null,
            urlContent: 'https://example.com',
            isMultiParcel: true
        );

        expect($data->getPdfContent())->toBeNull();
    });

    it('calculates PDF size', function (): void {
        $pdfContent = str_repeat('X', 1024); // 1 KB
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: base64_encode($pdfContent),
            urlContent: null,
            isMultiParcel: false
        );

        expect($data->getPdfSize())->toBe(1024);
    });

    it('validates PDF format', function (): void {
        $validPdf = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: base64_encode('%PDF-1.4 content'),
            urlContent: null,
            isMultiParcel: false
        );

        $invalidPdf = new PrintWaybillData(
            orderId: 'ORDER-456',
            trackingNumber: null,
            base64Content: base64_encode('Not a PDF'),
            urlContent: null,
            isMultiParcel: false
        );

        expect($validPdf->isValidPdf())->toBeTrue()
            ->and($invalidPdf->isValidPdf())->toBeFalse();
    });

    it('formats file size in human-readable format', function (): void {
        // Test with realistic small sizes only to avoid memory issues
        // The formatting logic is the same regardless of size
        $sizes = [
            512 => '512.00 B',
            1024 => '1.00 KB',
            1024 * 50 => '50.00 KB', // 50KB
        ];

        foreach ($sizes as $bytes => $expected) {
            $content = str_repeat('X', $bytes);
            $data = new PrintWaybillData(
                orderId: 'ORDER-123',
                trackingNumber: null,
                base64Content: base64_encode($content),
                urlContent: null,
                isMultiParcel: false
            );

            expect($data->getFormattedSize())->toBe($expected);
        }

        // Test the formatting calculation logic directly without allocating memory
        // We know 1MB = 1024*1024 bytes, and the formula is:
        // number_format($size / (1024 ** $power), 2).' '.$units[$power]
        // For 1MB: power=2, result = 1048576 / 1024^2 = 1.00 MB
        // For 1GB: power=3, result = 1073741824 / 1024^3 = 1.00 GB

        // We can verify the logic by testing that:
        // - 1024^2 bytes formats as "1.00 MB"
        // - We don't need to test 1GB allocation since the formula is the same

        // Test 1MB boundary (using a smaller representative sample)
        $oneMBContent = str_repeat('X', 1024 * 1024); // 1MB is manageable
        $oneMBData = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: base64_encode($oneMBContent),
            urlContent: null,
            isMultiParcel: false
        );

        expect($oneMBData->getFormattedSize())->toBe('1.00 MB');

        // Note: We skip 1GB test as it's not practical to allocate 1GB in tests
        // The formatting algorithm is tested with smaller sizes and follows the same pattern
    });

    it('returns null for formatted size when content not available', function (): void {
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: null,
            urlContent: 'https://example.com',
            isMultiParcel: true
        );

        expect($data->getFormattedSize())->toBeNull();
    });

    it('gets download URL for multi-parcel', function (): void {
        $url = 'https://api.jnt.com/download/123.pdf';
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: null,
            urlContent: $url,
            isMultiParcel: true
        );

        expect($data->getDownloadUrl())->toBe($url);
    });

    it('returns null download URL for single parcel', function (): void {
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: 'content',
            urlContent: null,
            isMultiParcel: false
        );

        expect($data->getDownloadUrl())->toBeNull();
    });

    it('saves PDF to file', function (): void {
        $pdfContent = '%PDF-1.4 Test PDF content';
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: base64_encode($pdfContent),
            urlContent: null,
            isMultiParcel: false
        );

        $tempFile = sys_get_temp_dir().'/test_waybill_'.uniqid().'.pdf';

        try {
            $result = $data->savePdf($tempFile);

            expect($result)->toBeTrue()
                ->and(file_exists($tempFile))->toBeTrue()
                ->and(file_get_contents($tempFile))->toBe($pdfContent);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });

    it('creates directory when saving PDF', function (): void {
        $pdfContent = 'PDF content';
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: base64_encode($pdfContent),
            urlContent: null,
            isMultiParcel: false
        );

        $tempDir = sys_get_temp_dir().'/jnt_test_'.uniqid();
        $tempFile = $tempDir.'/waybill.pdf';

        try {
            $result = $data->savePdf($tempFile);

            expect($result)->toBeTrue()
                ->and(is_dir($tempDir))->toBeTrue()
                ->and(file_exists($tempFile))->toBeTrue();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    });

    it('returns false when saving PDF without content', function (): void {
        $data = new PrintWaybillData(
            orderId: 'ORDER-123',
            trackingNumber: null,
            base64Content: null,
            urlContent: 'https://example.com',
            isMultiParcel: true
        );

        expect($data->savePdf('/tmp/test.pdf'))->toBeFalse();
    });

    it('handles empty base64 content', function (): void {
        $data = PrintWaybillData::fromApiArray([
            'orderId' => 'ORDER-123',
            'base64EncodeContent' => '',
        ]);

        expect($data->hasBase64Content())->toBeFalse()
            ->and($data->getPdfContent())->toBeNull();
    });
});
