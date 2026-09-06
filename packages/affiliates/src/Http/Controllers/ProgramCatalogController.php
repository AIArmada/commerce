<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\ProgramCatalogService;
use AIArmada\Affiliates\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ProgramCatalogController extends Controller
{
    public function __construct(
        private readonly ProgramService $programs,
        private readonly ProgramCatalogService $catalog,
    ) {}

    public function index(): JsonResponse
    {
        if (! (bool) config('affiliates.catalog.enabled', true)) {
            return response()->json(['message' => 'Catalog disabled'], 404);
        }

        $programs = $this->programs->getAvailablePrograms();

        return response()->json([
            'data' => $programs->map(fn (AffiliateProgram $p): array => [
                'program_id' => (string) $p->getKey(),
                'name' => $p->name,
                'slug' => $p->slug,
                'status' => $p->status->value ?? (string) $p->status,
                'currency' => $p->getAttribute('currency') ?? config('affiliates.currency.default', 'MYR'),
                'cookie_days' => $p->cookie_lifetime_days,
            ])->values()->all(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        if (! (bool) config('affiliates.catalog.enabled', true)) {
            return response()->json(['message' => 'Catalog disabled'], 404);
        }

        $program = AffiliateProgram::query()->whereKey($id)->first();

        if (! $program || ! $program->isActive() || ! $program->isOpen()) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        return response()->json($this->catalog->snapshot($program));
    }
}
