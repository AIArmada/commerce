<?php

declare(strict_types=1);

namespace AIArmada\Docs\Http\Controllers;

use AIArmada\Docs\Services\DocEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class DocTrackingController extends Controller
{
    public function __construct(
        private readonly DocEmailService $emailService,
    ) {}

    /**
     * Handle email open tracking pixel.
     * Returns a 1×1 transparent GIF. No auth required — token is self-contained.
     */
    public function open(string $token): Response
    {
        $this->emailService->trackOpen($token);

        // 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Handle email link click tracking then redirect.
     * No auth required — token is self-contained.
     */
    public function click(string $token): RedirectResponse
    {
        $url = $this->sanitizeRedirectUrl($this->emailService->trackClick($token));

        return redirect()->to($url);
    }

    private function sanitizeRedirectUrl(?string $url): string
    {
        $fallback = $this->fallbackRedirectUrl();

        if (! is_string($url)) {
            return $fallback;
        }

        $candidate = trim($url);

        if ($candidate === '' || str_starts_with($candidate, '//')) {
            return $fallback;
        }

        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            return $fallback;
        }

        $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $fallback;
        }

        return $candidate;
    }

    private function fallbackRedirectUrl(): string
    {
        $fallback = config('app.url', '/');

        if (! is_string($fallback) || trim($fallback) === '') {
            return '/';
        }

        return $fallback;
    }
}
