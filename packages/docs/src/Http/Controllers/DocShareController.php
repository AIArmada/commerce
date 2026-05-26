<?php

declare(strict_types=1);

namespace AIArmada\Docs\Http\Controllers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Services\DocRenderService;
use Symfony\Component\HttpFoundation\Response;

final class DocShareController
{
    public function show(string $token, DocRenderService $renderer): Response
    {
        $shareLink = $renderer->resolveShareLink($token, ShareLinkAction::View);

        return OwnerContext::withOwner(
            OwnerContext::fromTypeAndId($shareLink->owner_type, $shareLink->owner_id),
            fn (): Response => response(
                $renderer->renderHtml($shareLink->doc, RenderAudience::CustomerView)->toHtml(),
                200,
                $this->shareResponseHeaders([
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]),
            )
        );
    }

    public function pdf(string $token, DocRenderService $renderer): Response
    {
        $shareLink = $renderer->resolveShareLink($token, ShareLinkAction::Pdf);

        return OwnerContext::withOwner(
            OwnerContext::fromTypeAndId($shareLink->owner_type, $shareLink->owner_id),
            fn (): Response => response($renderer->renderPdf($shareLink->doc), 200, [
                ...$this->shareResponseHeaders(),
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $this->filename($shareLink->doc->doc_number) . '"',
            ])
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function shareResponseHeaders(array $headers = []): array
    {
        return array_merge([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ], $headers);
    }

    private function filename(string $docNumber): string
    {
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $docNumber) ?: 'document';
        $filename = mb_trim($filename, '-_');

        return ($filename === '' ? 'document' : $filename) . '.pdf';
    }
}
