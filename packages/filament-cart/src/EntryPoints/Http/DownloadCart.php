<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\EntryPoints\Http;

use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\FilamentCart\Actions\BuildCartExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadCart
{
    public function __construct(private BuildCartExport $buildExport) {}

    public function download(CartSnapshot $cart): StreamedResponse
    {
        $export = $this->buildExport->execute($cart);

        return response()->streamDownload(
            static function () use ($export): void {
                echo $export->json;
            },
            $export->filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
