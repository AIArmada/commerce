<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Actions\Affiliates\CreateTrackingLink;
use AIArmada\Affiliates\Contracts\AffiliateLookup;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AffiliateApiController extends Controller
{
    public function __construct(
        private readonly AffiliateLookup $affiliateLookup,
        private readonly CreateTrackingLink $createTrackingLink,
        private readonly AffiliateReportService $reports,
    ) {}

    public function summary(string $code): JsonResponse
    {
        $response = $this->requireOwnerContext();

        if ($response !== null) {
            return $response;
        }

        $affiliate = $this->affiliateLookup->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        return response()->json($this->reports->affiliateSummary($affiliate->getKey()));
    }

    public function links(string $code, Request $request): JsonResponse
    {
        $response = $this->requireOwnerContext();

        if ($response !== null) {
            return $response;
        }

        $affiliate = $this->affiliateLookup->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'url' => ['nullable', 'string', 'max:2048'],
            'link_style' => ['nullable', 'in:short,branded'],
            'link_label' => ['nullable', 'string', 'max:60'],
            'expires_at' => ['nullable', 'date'],
            'params' => ['nullable', 'array', 'max:50'],
            'params.*' => ['nullable', 'string', 'max:2048'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_key' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'string', 'max:255'],
            'subject_instance' => ['nullable', 'string', 'max:255'],
            'subject_title_snapshot' => ['nullable', 'string', 'max:255'],
            'subject_metadata' => ['nullable', 'array', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $validated = $validator->validated();

        $url = (string) ($validated['url'] ?? url('/'));
        $params = $validated['params'] ?? [];
        $subjectMetadata = $validated['subject_metadata'] ?? [];

        try {
            $link = $this->createTrackingLink->handle($affiliate, $url, [
                'params' => $params,
                'link_style' => $validated['link_style'] ?? config('affiliates.links.default_style', 'short'),
                'link_label' => $validated['link_label'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'subject_type' => $validated['subject_type'] ?? null,
                'subject_key' => $validated['subject_key'] ?? null,
                'subject_id' => $validated['subject_id'] ?? null,
                'subject_instance' => $validated['subject_instance'] ?? null,
                'subject_title_snapshot' => $validated['subject_title_snapshot'] ?? null,
                'subject_metadata' => $subjectMetadata,
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => (string) $link->getKey(),
            'link' => $link->tracking_url,
            'subject_type' => $link->subject_type,
            'subject_key' => $link->subject_key,
        ]);
    }

    public function creatives(string $code): JsonResponse
    {
        $response = $this->requireOwnerContext();

        if ($response !== null) {
            return $response;
        }

        $affiliate = $this->affiliateLookup->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $creatives = $affiliate->metadata['creatives'] ?? [];

        return response()->json([
            'creatives' => $creatives,
        ]);
    }

    private function requireOwnerContext(): ?JsonResponse
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return null;
        }

        try {
            OwnerContext::assertResolvedOrExplicitGlobal(
                OwnerContext::resolve(),
                'Owner context required',
            );

            return null;
        } catch (NoCurrentOwnerException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
