<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Support\LoginMigrationCacheKey;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;

final class HandleUserLogin
{
    public function __construct(
        private CartMigrationService $migrationService
    ) {}

    /**
     * Handle the user login event
     */
    public function handle(Login $event): void
    {
        // Try to retrieve the old session ID from cache
        $oldSessionId = null;

        foreach ($this->getUserIdentifiers($event->user) as $userIdentifier) {
            $oldSessionId = Cache::pull(LoginMigrationCacheKey::make($userIdentifier));

            if ($oldSessionId !== null) {
                break;
            }
        }

        // Migrate guest cart to user cart using old session ID
        /** @var object{success: bool, itemsMerged: int, conflicts: mixed, message: string} $result */
        $result = $this->migrationService->migrateGuestCartForUser($event->user, 'default', $oldSessionId);

        if ($result->success && $result->itemsMerged > 0) {
            // Store migration result in session for potential display to user
            session()->flash('cart_migration', [
                'items_merged' => $result->itemsMerged,
                'has_conflicts' => false, // Simplified
                'conflicts' => $result->conflicts,
                'message' => $result->message ?? 'Cart migration completed',
            ]);
        }
    }

    /**
     * Extract possible user identifiers from the authenticated user.
     *
     * @return array<int, string>
     */
    private function getUserIdentifiers(mixed $user): array
    {
        return collect([
            $user->email ?? null,
            $user->username ?? null,
            $user->phone ?? null,
        ])
            ->filter(fn (mixed $identifier): bool => is_string($identifier) && $identifier !== '')
            ->unique()
            ->values()
            ->all();
    }
}
