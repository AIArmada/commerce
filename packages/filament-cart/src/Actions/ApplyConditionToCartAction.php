<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Actions;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\Condition;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Exception;
use Illuminate\Database\Eloquent\Builder;

final class ApplyConditionToCartAction
{
    public function __construct(
        private readonly CartInstanceManager $cartInstanceManager,
        private readonly RulesFactoryInterface $rulesFactory,
    ) {}

    /**
     * Apply a stored condition to a cart
     *
     * @throws Exception
     */
    public function apply(Cart $cart, string $conditionId, ?string $customName = null): CartCondition
    {
        $conditionModel = $this->findStoredCondition($conditionId, forItems: false);
        $condition = $conditionModel->createCondition($customName);

        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        $cartInstance->addCondition($condition);

        return $condition;
    }

    /**
     * Apply a stored condition to a specific cart item
     *
     * @throws Exception
     */
    public function applyToItem(Cart $cart, string $itemId, string $conditionId, ?string $customName = null): CartCondition
    {
        $conditionModel = $this->findStoredCondition($conditionId, forItems: true);
        $condition = $conditionModel->createCondition($customName);

        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);
        $success = $cartInstance->addItemCondition($itemId, $condition);

        if (! $success) {
            throw new Exception('Item not found in cart');
        }

        return $condition;
    }

    /**
     * Apply a custom condition to a cart
     *
     * @param  array<string, mixed>  $data
     * @throws Exception
     */
    public function applyCustom(Cart $cart, array $data): CartCondition
    {
        $cartInstance = $this->cartInstanceManager->resolveForSnapshot($cart);

        $rulesDefinition = Condition::normalizeRulesDefinition(
            $data['dynamic_rules'] ?? null,
            ! empty($data['is_dynamic'])
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
                    $this->rulesFactory->createRules($factoryKey, ['context' => $rulesDefinition['context']])
                );
            }
        }

        // Merge custom attributes with source marker
        $attributes = array_merge(
            $data['attributes'] ?? [],
            ['source' => 'custom']
        );

        // Create condition manually
        $condition = new CartCondition(
            name: $data['name'],
            type: $data['type'],
            target: $data['target'],
            value: $data['value'],
            attributes: $attributes,
            order: (int) ($data['order'] ?? 0),
            rules: $rules
        );

        // Apply or register the condition based on dynamic rules
        if ($rulesDefinition !== null) {
            $factoryKeys = $rulesDefinition['factory_keys'];

            $cartInstance->registerDynamicCondition(
                $condition,
                ruleFactoryKey: count($factoryKeys) === 1 ? $factoryKeys[0] : $factoryKeys,
                metadata: [
                    'context' => $rulesDefinition['context'],
                ]
            );
        } else {
            $cartInstance->addCondition($condition);
        }

        return $condition;
    }

    /**
     * Find a stored condition by ID
     *
     * @throws Exception
     */
    private function findStoredCondition(string $conditionId, bool $forItems): Condition
    {
        $query = $this->getScopedConditionQuery($forItems);

        $condition = $query->find($conditionId);

        if (! $condition) {
            throw new Exception('Condition not found or access denied');
        }

        return $condition;
    }

    /**
     * Get scoped condition query
     *
     * @return Builder<Condition>
     */
    private function getScopedConditionQuery(bool $forItems): Builder
    {
        $query = Condition::query()->active();

        if ($forItems) {
            $query->forItems();
        }

        if (! Condition::ownerScopingEnabled()) {
            return $query;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            Condition::class . ' requires an owner context or explicit global context.',
        );

        if ($owner === null) {
            return $query->globalOnly();
        }

        return $query->forOwner($owner, (bool) config('cart.owner.include_global', false));
    }
}
