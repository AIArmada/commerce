<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

final class AffiliateApiController extends Controller
{
    public function __construct(
        private readonly AffiliateService $affiliates,
        private readonly AffiliateReportService $reports,
    ) {}

    public function summary(string $code): JsonResponse
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('affiliates.owner.include_global', false);

            if ($owner === null && ! $includeGlobal) {
                return response()->json(['message' => 'Owner context required'], 400);
            }
        }

        $affiliate = $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        return response()->json($this->reports->affiliateSummary($affiliate->getKey()));
    }

    public function links(string $code, Request $request): JsonResponse
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('affiliates.owner.include_global', false);

            if ($owner === null && ! $includeGlobal) {
                return response()->json(['message' => 'Owner context required'], 400);
            }
        }

        $affiliate = $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $url = (string) $request->input('url', url('/'));
        $ttl = null;
        if ($request->filled('ttl')) {
            $ttlValue = $request->integer('ttl');
            $ttl = $ttlValue > 0 ? $ttlValue : null;
        }
        $params = (array) $request->input('params', []);
        $subjectMetadata = $request->input('subject_metadata', []);

        if (! is_array($subjectMetadata)) {
            $subjectMetadata = [];
        }

        try {
            $link = $this->affiliates->createTrackingLink($affiliate, $url, [
                'params' => $params,
                'ttl_seconds' => $ttl,
                'subject_type' => $request->input('subject_type'),
                'subject_identifier' => $request->input('subject_identifier'),
                'subject_instance' => $request->input('subject_instance'),
                'subject_title_snapshot' => $request->input('subject_title_snapshot'),
                'subject_metadata' => $subjectMetadata,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => (string) $link->getKey(),
            'link' => $link->tracking_url,
            'subject_type' => $link->subject_type,
            'subject_identifier' => $link->subject_identifier,
        ]);
    }

    public function creatives(string $code): JsonResponse
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('affiliates.owner.include_global', false);

            if ($owner === null && ! $includeGlobal) {
                return response()->json(['message' => 'Owner context required'], 400);
            }
        }

        $affiliate = $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $creatives = $affiliate->metadata['creatives'] ?? [];

        return response()->json([
            'creatives' => $creatives,
        ]);
    }
}
