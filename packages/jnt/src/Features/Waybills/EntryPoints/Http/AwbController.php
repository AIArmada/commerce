<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Waybills\EntryPoints\Http;

use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\FileSignature;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AwbController
{
    public function show(Request $request, string $orderId): SymfonyResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        /** @var array<string, mixed>|null $data */
        $data = app(SignedDownloadApi::class)->payloadFromToken($request->query(SignedDownloadApi::TOKEN_QUERY_PARAM), 'jnt_awb');

        if ($data === null || ! is_string($data['content'] ?? null) || $data['content'] === '') {
            abort(404, 'AWB not found or expired.');
        }

        if (! app(SignedDownloadApi::class)->isAuthorizedPayload(
            payload: $data,
            resourceIdKey: 'order_id',
            expectedResourceId: $orderId,
            owner: EloquentRecordReference::fromModel(OwnerContext::resolve()),
            userId: $request->user()?->getAuthIdentifier(),
        )) {
            abort(403, 'Unauthorized AWB access.');
        }

        $format = $data['format'] ?? null;

        if (! in_array($format, ['pdf', 'png', 'zpl'], true)) {
            abort(404, 'Unsupported AWB format.');
        }

        if (($format === 'pdf' && ! FileSignature::isPdf($data['content']))
            || ($format === 'png' && ! FileSignature::isPng($data['content']))) {
            abort(404, 'Invalid AWB content.');
        }

        $mimeType = match ($format) {
            'png' => 'image/png',
            'zpl' => 'application/octet-stream',
            default => 'application/pdf',
        };

        $filename = self::safeFilename($orderId, $format);

        return new Response($data['content'], 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function safeFilename(string $orderId, string $format): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $orderId) ?? '';

        $base = mb_trim($base, '._');

        if ($base === '') {
            $base = 'waybill';
        }

        return 'jnt_awb_' . mb_substr($base, 0, 100) . '.' . $format;
    }
}
