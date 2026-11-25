<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\EntryPoints\Http;

use AIArmada\Affiliates\Exceptions\ProgramEnrollmentDenied;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ProgramMembershipController extends Controller
{
    public function __construct(
        private readonly AffiliateDirectory $affiliates,
        private readonly ProgramEnrollment $enrollment,
        private readonly ProgramMembershipReader $memberships,
    ) {}

    public function join(string $code, string $id): JsonResponse
    {
        $guard = $this->requireOwnerContext();

        if ($guard !== null) {
            return $guard;
        }

        $affiliate = $this->affiliates->findByCode($code);

        if ($affiliate === null) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        if (! $this->memberships->programExists($id)) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        try {
            $membership = $this->enrollment->join($affiliate->id, $id);
        } catch (ProgramEnrollmentDenied $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'member' => true,
            'status' => $membership->status,
            'tier_id' => $membership->tierId,
        ], $membership->created ? 201 : 200);
    }

    public function membership(string $code, string $id): JsonResponse
    {
        $guard = $this->requireOwnerContext();

        if ($guard !== null) {
            return $guard;
        }

        $affiliate = $this->affiliates->findByCode($code);

        if ($affiliate === null) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        if (! $this->memberships->programExists($id)) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        $membership = $this->memberships->find($affiliate->id, $id);

        if ($membership === null) {
            return response()->json(['member' => false, 'status' => null]);
        }

        return response()->json([
            'member' => true,
            'status' => $membership->status,
            'tier_id' => $membership->tierId,
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
