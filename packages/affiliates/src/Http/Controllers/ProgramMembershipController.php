<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers;

use AIArmada\Affiliates\Contracts\AffiliateLookup;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * Headless program enrollment: join a program and read membership state.
 *
 * The Filament portal covers human affiliates; these endpoints cover
 * storefronts and integrations. Joins are idempotent — rejoining returns
 * the existing membership.
 */
final class ProgramMembershipController extends Controller
{
    public function __construct(
        private readonly AffiliateLookup $affiliateLookup,
        private readonly ProgramService $programs,
    ) {}

    public function join(string $code, string $id): JsonResponse
    {
        $guard = $this->requireOwnerContext();

        if ($guard !== null) {
            return $guard;
        }

        $affiliate = $this->affiliateLookup->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $program = AffiliateProgram::query()->whereKey($id)->first();

        if (! $program instanceof AffiliateProgram) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        try {
            $membership = $this->programs->joinProgram($affiliate, $program);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $status = $membership->status;

        return response()->json([
            'member' => true,
            'status' => $status instanceof BackedEnum ? $status->value : (string) $status,
            'tier_id' => $membership->tier_id !== null ? (string) $membership->tier_id : null,
        ], $membership->wasRecentlyCreated ? 201 : 200);
    }

    public function membership(string $code, string $id): JsonResponse
    {
        $guard = $this->requireOwnerContext();

        if ($guard !== null) {
            return $guard;
        }

        $affiliate = $this->affiliateLookup->findByCode($code);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $program = AffiliateProgram::query()->whereKey($id)->first();

        if (! $program instanceof AffiliateProgram) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        $membership = $this->programs->getMembership($affiliate, $program);

        if ($membership === null) {
            return response()->json(['member' => false, 'status' => null]);
        }

        $status = $membership->status;

        return response()->json([
            'member' => true,
            'status' => $status instanceof BackedEnum ? $status->value : (string) $status,
            'tier_id' => $membership->tier_id !== null ? (string) $membership->tier_id : null,
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
