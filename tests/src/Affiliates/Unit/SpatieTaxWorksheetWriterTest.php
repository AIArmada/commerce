<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\TaxRecipientDetails;
use AIArmada\Affiliates\Data\TaxWorksheetRequest;
use AIArmada\Affiliates\Drivers\Tax\SpatieTaxWorksheetWriter;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Drivers\BrowsershotDriver;
use Spatie\LaravelPdf\Drivers\PdfDriver;
use Spatie\LaravelPdf\PdfOptions;
use Symfony\Component\Process\Process;

/** @param array<string, mixed> $data */
function taxWorksheetRequest(array $data): TaxWorksheetRequest
{
    return new TaxWorksheetRequest(
        (string) $data['affiliate']->getKey(),
        $data['year'],
        $data['total_amount'],
        $data['currency'] ?? 'USD',
        new TaxRecipientDetails($data['tax_info']['legal_name'], $data['tax_info']['tin'], $data['tax_info']['address'] ?? ''),
    );
}

beforeEach(function (): void {
    $driver = Mockery::mock(PdfDriver::class);
    $driver->shouldReceive('generatePdf')->andReturnUsing(static fn (string $html, ?string $header, ?string $footer, PdfOptions $options): string => "%PDF-1.7\n" . $html);
    app()->instance('laravel-pdf.driver.browsershot', $driver);
    $this->generator = new SpatieTaxWorksheetWriter;

    $this->affiliate = Affiliate::create([
        'code' => 'TAX-' . uniqid(),
        'name' => 'Tax Test Affiliate',
        'contact_email' => 'tax@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('SpatieTaxWorksheetWriter', function (): void {
    describe('generate', function (): void {
        test('generates tax document and returns path', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 150000, // $1500.00
                'tax_info' => [
                    'legal_name' => 'John Doe',
                    'address' => '123 Main St',
                    'tin' => '123-45-6789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));

            expect($result)->toContain('tax-documents/2024/');
            expect($result)->toContain('1099-NEC-2024-');
            expect($result)->toContain($this->affiliate->id);
            expect($result)->toEndWith('.pdf');

            Storage::disk('local')->assertExists($result);
            expect(Storage::disk('local')->get($result))->toStartWith('%PDF-');
            expect(Storage::disk('local')->getVisibility($result))->toBe('private');
        });

        test('stores document in year-specific folder', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2023,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Jane Smith',
                    'tin' => '987654321',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));

            expect($result)->toStartWith('tax-documents/2023/');
        });

        test('creates a unique private worksheet for each generation', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Test User',
                    'tin' => '111223333',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));

            expect($result)->toContain('1099-NEC-2024-' . $this->affiliate->id . '-')
                ->and($this->generator->write(taxWorksheetRequest($data)))->not->toBe($result);
        });
    });

    describe('Worksheet content generation', function (): void {
        test('includes payer information from config', function (): void {
            Storage::fake('local');

            config([
                'affiliates.tax.payer_info' => [
                    'name' => 'Test Company Inc',
                    'address' => '456 Business Blvd',
                    'tin' => '98-7654321',
                ],
            ]);

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 500000,
                'tax_info' => [
                    'legal_name' => 'Recipient Name',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('Test Company Inc');
            expect($content)->toContain('456 Business Blvd');
        });

        test('includes recipient legal name', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'John Q. Public',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('John Q. Public');
        });

        test('includes recipient address when provided', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Affiliate Person',
                    'address' => '789 Affiliate Ave, Suite 100',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('789 Affiliate Ave, Suite 100');
        });

        test('handles missing address gracefully', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'No Address Person',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('Address: </p>');
        });

        test('formats amount correctly in dollars', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 1234567, // $12,345.67
                'tax_info' => [
                    'legal_name' => 'High Earner',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('$12,345.67');
        });

        test('includes form title and year', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Test',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('not an official IRS filing');
            expect($content)->toContain('Tax Year: 2024');
        });
    });

    describe('TIN masking', function (): void {
        test('masks SSN format correctly', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'SSN Person',
                    'tin' => '123-45-6789', // SSN format
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            // SSN: ***-**-6789
            expect($content)->toContain('***-**-6789');
            expect($content)->not->toContain('123-45-6789');
        });

        test('masks SSN without dashes correctly', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'SSN Person',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('***-**-6789');
        });

        test('masks EIN format correctly', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Business Entity',
                    'tin' => '12-3456789', // EIN format (only 8 digits after removing -)
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            expect($content)->toContain('***-**-6789');
        });

        test('handles short TIN', function (): void {
            Storage::fake('local');

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Short TIN',
                    'tin' => '12345678', // Only 8 digits
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));
            $content = Storage::disk('local')->get($result);

            // Should use alternate masking format
            expect($content)->toContain('***-**-5678');
        });
    });

    describe('storage configuration', function (): void {
        test('uses configured storage disk', function (): void {
            Storage::fake('custom-tax-disk');

            config(['affiliates.tax.storage_disk' => 'custom-tax-disk']);

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Custom Disk Test',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));

            Storage::disk('custom-tax-disk')->assertExists($result);
        });

        test('defaults to local disk when not configured', function (): void {
            Storage::fake('local');

            config(['affiliates.tax.storage_disk' => null]);

            $data = [
                'affiliate' => $this->affiliate,
                'year' => 2024,
                'total_amount' => 100000,
                'tax_info' => [
                    'legal_name' => 'Local Disk Test',
                    'tin' => '123456789',
                ],
            ];

            $result = $this->generator->write(taxWorksheetRequest($data));

            Storage::disk('local')->assertExists($result);
        });
    });
    test('rejects invalid worksheet identities and refuses unrelated file deletion', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('unrelated/private.pdf', 'private data');

        $request = new TaxWorksheetRequest('../foreign', 2024, 70000, 'USD', new TaxRecipientDetails('Recipient', '123456789'));

        expect(fn () => $this->generator->write($request))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->generator->delete('unrelated/private.pdf'))->toThrow(InvalidArgumentException::class);
        Storage::disk('local')->assertExists('unrelated/private.pdf');
    });

    test('rejects remote drivers before sending sensitive data', function (): void {
        config(['affiliates.tax.pdf_driver' => 'cloudflare']);
        $request = new TaxWorksheetRequest((string) $this->affiliate->getKey(), 2024, 70000, 'USD', new TaxRecipientDetails('Recipient', '123456789'));

        expect(fn () => $this->generator->write($request))->toThrow(RuntimeException::class, 'local PDF driver');
    });

    test('rejects invalid renderer content', function (): void {
        Storage::fake('local');
        $driver = Mockery::mock(PdfDriver::class);
        $driver->shouldReceive('generatePdf')->andReturn('not a PDF');
        app()->instance('laravel-pdf.driver.browsershot', $driver);

        $request = new TaxWorksheetRequest((string) $this->affiliate->getKey(), 2024, 70000, 'USD', new TaxRecipientDetails('Recipient', '123456789'));

        expect(fn () => $this->generator->write($request))->toThrow(RuntimeException::class, 'did not return a PDF');
        expect(Storage::disk('local')->allFiles())->toBe([]);
    });
});

test('renders a real private tax worksheet with Browsershot', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.pdf_driver' => 'browsershot', 'affiliates.tax.storage_disk' => 'local']);
    app()->instance('laravel-pdf.driver.browsershot', new BrowsershotDriver([
        'chrome_path' => getenv('AFFILIATE_PDF_CHROME'),
        'node_modules_path' => getenv('AFFILIATE_PDF_NODE_MODULES'),
        'no_sandbox' => true,
    ]));

    $path = $this->generator->write(new TaxWorksheetRequest(
        (string) $this->affiliate->getKey(),
        2024,
        123456,
        'USD',
        new TaxRecipientDetails('Synthetic PDF Recipient', '123-45-6789', '123 Test Street'),
        'Synthetic review notes',
    ));

    $disk = Storage::disk('local');
    $bytes = $disk->get($path);
    expect($bytes)->toStartWith('%PDF-')
        ->and(mb_trim($bytes))->toEndWith('%%EOF')
        ->and($disk->getVisibility($path))->toBe('private');

    $process = new Process(['pdftotext', $disk->path($path), '-']);
    $process->mustRun();

    expect($process->getOutput())->toContain('Synthetic PDF Recipient')
        ->toContain('***-**-6789')
        ->toContain('$1,234.56')
        ->toContain('not an official IRS filing')
        ->toContain('Synthetic review notes')
        ->not->toContain('123-45-6789');
})->skip(fn (): bool => getenv('AFFILIATE_REAL_PDF_TEST') !== '1');
