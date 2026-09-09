<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\Condition;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Exception;
use Illuminate\Database\Eloquent\Builder;

final class ApplyStoredCondition
{
    public function __construct(
        private readonly CartInstanceManager $cartInstanceManager,
        private readonly RulesFactoryInterface $rulesFactory,
    ) {}

    /**
     * @throws Exception
     */
    public function apply(CartSnapshot $cart, string $conditionId, ?string $customName = null): CartCondition
    {
        $cart = $this->authorizeCart($cart);
        $conditionModel = $this->findStoredCondition($conditionId, forItems: false);
        $condition = $conditionModel->createCondition($customName);

        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        $cartInstance->addCondition($condition);

        return $condition;
    }

    /**
     * @throws Exception
     */
    public function applyToItem(
        CartSnapshot $cart,
        string $itemId,
        string $conditionId,
        ?string $customName = null,
    ): CartCondition {
        $cart = $this->authorizeCart($cart);
        $conditionModel = $this->findStoredCondition($conditionId, forItems: true);
        $condition = $conditionModel->createCondition($customName);

        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        if (! $cartInstance->addItemCondition($itemId, $condition)) {
            throw new Exception('Item not found in cart');
        }

        return $condition;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws Exception
     */
    public function applyCustom(CartSnapshot $cart, array $data): CartCondition
    {
        $cart = $this->authorizeCart($cart);
        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);

        $rulesDefinition = Condition::normalizeRulesDefinition(
            $data['dynamic_rules'] ?? null,
            ! empty($data['is_dynamic']),
        );

        $rules = null;
        if ($rulesDefinition !== null) {
            $rules = [];

            foreach ($rulesDefinition['factory_keys'] as $factoryKey) {
                if (! $this->rulesFactory->canCreateRules($factoryKey)) {
                    throw new Exception("Unsupported rule factory key [{$factoryKey}]");
                }

                $rules = array_merge(
                    $rules,
                    $this->rulesFactory->createRules($factoryKey, ['context' => $rulesDefinition['context']]),
                );
            }
        }

        $attributes = array_merge(
            is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            ['source' => 'custom'],
        );

        $condition = new CartCondition(
            name: (string) $data['name'],
            type: (string) $data['type'],
            target: (string) $data['target'],
            value: $data['value'],
            attributes: $attributes,
            order: (int) ($data['order'] ?? 0),
            rules: $rules,
        );

        if ($rulesDefinition !== null) {
            $factoryKeys = $rulesDefinition['factory_keys'];

            $cartInstance->registerDynamicCondition(
                $condition,
                ruleFactoryKey: count($factoryKeys) === 1 ? $factoryKeys[0] : $factoryKeys,
                metadata: ['context' => $rulesDefinition['context']],
            );
        } else {
            $cartInstance->addCondition($condition);
        }

        return $condition;
    }

    private function authorizeCart(CartSnapshot $cart): CartSnapshot
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return $cart;
        }

        /** @var CartSnapshot $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            CartSnapshot::class,
            (string) $cart->getKey(),
            includeGlobal: false,
            message: 'Cart is not accessible in the current owner scope.',
        );

        return $validated;
    }

    /**
     * @throws Exception
     */
    private function findStoredCondition(string $conditionId, bool $forItems): Condition
    {
        if (! Condition::ownerScopingEnabled()) {
            $condition = $this->baseConditionQuery($forItems)->find($conditionId);

            if (! $condition instanceof Condition) {
                throw new Exception('Condition not found or access denied');
            }

            return $condition;
        }

        /** @var Condition $condition */
        $condition = OwnerWriteGuard::findOrFailForOwner(
            Condition::class,
            $conditionId,
            includeGlobal: (bool) config('cart.owner.include_global', false),
            message: 'Condition is not accessible in the current owner scope.',
        );

        if (! $condition->is_active) {
            throw new Exception('Condition is not active.');
        }

        if ($forItems && ! str_starts_with((string) $condition->target, 'items@')) {
            throw new Exception('Condition is not an item-level condition.');
        }

        return $condition;
    }

    /**
     * @return Builder<Condition>
     */
    private function baseConditionQuery(bool $forItems): Builder
    {
        $query = Condition::query()->active();

        if ($forItems) {
            $query->forItems();
        }

        return $query;
    }
}
