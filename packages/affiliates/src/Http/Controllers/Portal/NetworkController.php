<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\NetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class NetworkController extends Controller
{
    public function __construct(
        private readonly NetworkService $networkService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $depth = (int) $request->get('depth', 3);
        $tree = $this->networkService->buildTree($affiliate, $depth);

        return response()->json([
            'tree' => $tree,
            'stats' => $this->getNetworkStats($affiliate),
        ]);
    }

    public function upline(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $upline = $this->networkService->getUpline($affiliate);

        return response()->json([
            'upline' => $upline->map(fn (Affiliate $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'code' => $a->code,
                'rank' => $a->rank?->name,
                'level' => $a->pivot?->depth ?? 0,
            ])->all(),
        ]);
    }

    public function downline(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $level = $request->get('level');
        $perPage = (int) $request->get('per_page', 20);

        $query = $this->networkService->getDownline($affiliate);

        if ($level !== null) {
            $query->wherePivot('depth', (int) $level);
        }

        $downline = $query->paginate($perPage);

        return response()->json([
            'data' => $downline->map(fn (Affiliate $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'code' => $a->code,
                'status' => $a->status->value,
                'rank' => $a->rank?->name,
                'level' => $a->pivot?->depth ?? 0,
                'joined_at' => $a->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $downline->currentPage(),
                'last_page' => $downline->lastPage(),
                'per_page' => $downline->perPage(),
                'total' => $downline->total(),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        return response()->json($this->getNetworkStats($affiliate));
    }

    private function getNetworkStats(Affiliate $affiliate): array
    {
        $downline = $this->networkService->getDownline($affiliate)->get();

        $totalMembers = $downline->count();
        $activeMembers = $downline->where('status', 'active')->count();

        $byLevel = $downline->groupBy(fn ($a) => $a->pivot?->depth ?? 0)
            ->map->count()
            ->all();

        $thisMonthJoined = $downline
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $networkRevenue = 0;
        foreach ($downline as $member) {
            $networkRevenue += $member->conversions()
                ->where('occurred_at', '>=', now()->startOfMonth())
                ->sum('total_minor');
        }

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'inactive_members' => $totalMembers - $activeMembers,
            'by_level' => $byLevel,
            'joined_this_month' => $thisMonthJoined,
            'network_revenue_minor' => $networkRevenue,
        ];
    }
}
