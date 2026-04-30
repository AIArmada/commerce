<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Actions;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadTaxExemptionCertificateAction
{
    public function execute(TaxExemption $exemption): StreamedResponse
    {
        if (! TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())->whereKey($exemption->getKey())->exists()) {
            throw new NotFoundHttpException('Certificate document not found.');
        }

        $path = $exemption->document_path;

        if (! $path) {
            throw new NotFoundHttpException('No certificate document found.');
        }

        $path = Str::ltrim($path, '/');

        $allowedPrefix = mb_rtrim((string) config('filament-tax.certificates.directory', 'tax-exemptions'), '/') . '/';

        if (
            (! Str::startsWith($path, $allowedPrefix))
            || Str::contains($path, ['..', "\0"])
        ) {
            throw new NotFoundHttpException('Invalid certificate path.');
        }

        $disk = Storage::disk((string) config('filament-tax.certificates.disk', 'local'));

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException('Certificate document not found.');
        }

        /** @var StreamedResponse $response */
        $response = $disk->download($path, basename($path));

        return $response;
    }
}
