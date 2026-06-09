<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Contracts\CartMergeStrategyInterface;
use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use Exception;
use Illuminate\Database\Eloquent\Model;

class MigrateCartOnLoginAction
{
    private ?CartMergeStrategyInterface $mergeStrategy = null;

    public function __construct(
        private readonly MigrateGuestCartToUserAction $migrationAction,
        private readonly LoginMigrationIdentifierResolver $identifierResolver,
    ) {}

    public function withMergeStrategy(CartMergeStrategyInterface $strategy): static
    {
        $this->mergeStrategy = $strategy;

        return $this;
    }

    /**
     * @return array{success: bool, itemsMerged: int, message: string}
     */
    public function execute(mixed $user, ?string $instance = 'default', ?string $sessionId = null): array
    {
        if ($sessionId === null) {
            $identifiers = $this->identifierResolver->resolveFromUser($user);
            $sessionId = $this->identifierResolver->findCachedSessionId($identifiers);
        }

        if ($sessionId === null) {
            return [
                'success' => false,
                'itemsMerged' => 0,
                'message' => 'No guest session to migrate',
            ];
        }

        $userId = $user instanceof Model
            ? $user->getKey()
            : (is_object($user) && isset($user->id) ? $user->id : $user);

        try {
            $action = $this->migrationAction;

            if ($this->mergeStrategy !== null) {
                $action = $action->withMergeStrategy($this->mergeStrategy);
            }

            $success = $action->execute($userId, $instance, $sessionId);

            return [
                'success' => $success,
                'itemsMerged' => $success ? 1 : 0,
                'message' => $success ? 'Cart migration completed successfully' : 'No items to migrate',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'itemsMerged' => 0,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ];
        }
    }
}
