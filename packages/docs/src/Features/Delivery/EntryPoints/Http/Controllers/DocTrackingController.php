<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Delivery\EntryPoints\Http\Controllers;

use AIArmada\Docs\Features\Delivery\Actions\TrackDocEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class DocTrackingController extends Controller
{
    public function __construct(
        private readonly TrackDocEmail $tracking,
    ) {}

    /**
     * Handle email open tracking pixel.
     * Returns a 1×1 transparent GIF. No auth required — token is self-contained.
     */
    public function open(string $token): Response
    {
        $this->tracking->trackOpen($token);

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
        $url = $this->tracking->trackClick($token) ?? $this->fallbackRedirectUrl();

        return redirect()->to($url)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function fallbackRedirectUrl(): string
    {
        $fallback = config('app.url', '/');

        if (! is_string($fallback) || mb_trim($fallback) === '') {
            return '/';
        }

        return $fallback;
    }
}
