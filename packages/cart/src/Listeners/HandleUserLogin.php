<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Services\CartMigrationService;
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
        $userIdentifier = $this->getUserIdentifier($event->user);
        $oldSessionId = null;

        if ($userIdentifier) {
            $oldSessionId = Cache::pull("cart_migration_{$userIdentifier}");
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
     * Extract user identifier from user object.
     */
    private function getUserIdentifier(mixed $user): ?string
    {
        // Try common user identifier fields
        return $user->email
            ?? $user->username
            ?? $user->phone
            ?? null;
    }
}
