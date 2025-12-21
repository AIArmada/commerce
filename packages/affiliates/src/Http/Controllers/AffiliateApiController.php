<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
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
        private readonly AffiliateLinkGenerator $links
    ) {}

    public function summary(string $code): JsonResponse
    {
        $affiliate = config('affiliates.owner.enabled', false)
            ? $this->affiliates->findByCodeWithoutOwnerScope($code)
            : $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $owner = OwnerContext::fromTypeAndId($affiliate->owner_type, $affiliate->owner_id);

        return OwnerContext::withOwner(
            $owner,
            fn (): JsonResponse => response()->json($this->reports->affiliateSummary($affiliate->getKey()))
        );
    }

    public function links(string $code, Request $request): JsonResponse
    {
        $affiliate = config('affiliates.owner.enabled', false)
            ? $this->affiliates->findByCodeWithoutOwnerScope($code)
            : $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $owner = OwnerContext::fromTypeAndId($affiliate->owner_type, $affiliate->owner_id);

        $url = (string) $request->query('url', url('/'));
        $ttl = $request->integer('ttl', null);
        $params = (array) $request->query('params', []);

        try {
            $link = OwnerContext::withOwner(
                $owner,
                fn (): string => $this->links->generate($affiliate->code, $url, $params, $ttl ?: null)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['link' => $link]);
    }

    public function creatives(string $code): JsonResponse
    {
        $affiliate = config('affiliates.owner.enabled', false)
            ? $this->affiliates->findByCodeWithoutOwnerScope($code)
            : $this->affiliates->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $owner = OwnerContext::fromTypeAndId($affiliate->owner_type, $affiliate->owner_id);

        return OwnerContext::withOwner($owner, function () use ($affiliate): JsonResponse {
            $creatives = $affiliate->metadata['creatives'] ?? [];

            return response()->json([
                'creatives' => $creatives,
            ]);
        });
    }
}
