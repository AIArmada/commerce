<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\EntryPoints\Http;

use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ProgramCatalogController extends Controller
{
    public function __construct(private readonly MerchantCatalog $catalog) {}

    public function index(): JsonResponse
    {
        if (! (bool) config('affiliates.catalog.enabled', true)) {
            return response()->json(['message' => 'Catalog disabled'], 404);
        }

        return response()->json(['data' => $this->catalog->availablePrograms()]);
    }

    public function show(string $id): JsonResponse
    {
        if (! (bool) config('affiliates.catalog.enabled', true)) {
            return response()->json(['message' => 'Catalog disabled'], 404);
        }

        $snapshot = $this->catalog->snapshotById($id);

        return $snapshot === null
            ? response()->json(['message' => 'Program not found'], 404)
            : response()->json($snapshot->payload);
    }
}
