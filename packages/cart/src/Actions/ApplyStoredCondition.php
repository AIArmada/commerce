<?php

declare(strict_types=1);

namespace AIArmada\Cart\Actions;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use AIArmada\Cart\Models\Condition;
use AIArmada\Cart\Snapshots\CartInstanceManager;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Support\CartLimits;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

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
     * Apply an ad-hoc custom condition to a cart.
     *
     * Required keys in $data: name, type, target (condition-target DSL), value.
     * Optional keys: order (int), attributes (array), is_dynamic (bool),
     * dynamic_rules (factory_keys allowlist + context).
     *
     * Authorization contract: this action validates shape and value syntax but
     * does NOT decide who may grant discounts or charges. Every caller must
     * authorize first — the Filament caller enforces this through its resource
     * policies and constrained form schema before invoking this action.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Exception
     */
    public function applyCustom(CartSnapshot $cart, array $data): CartCondition
    {
        $cart = $this->authorizeCart($cart);
        $validated = $this->validateCustomData($data);
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

        try {
            $condition = new CartCondition(
                name: $validated['name'],
                type: $validated['type'],
                target: ConditionTarget::from($validated['target']),
                value: $validated['value'],
                attributes: $attributes,
                order: $validated['order'],
                rules: $rules,
            );
        } catch (InvalidCartConditionException | InvalidArgumentException $exception) {
            throw new Exception($exception->getMessage(), previous: $exception);
        }

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

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, type: string, target: string, value: string|int|float, order: int}
     *
     * @throws Exception
     */
    private function validateCustomData(array $data): array
    {
        $limits = CartLimits::fromConfig();

        $name = $data['name'] ?? null;
        $type = $data['type'] ?? null;
        $target = $data['target'] ?? null;
        $value = $data['value'] ?? null;

        if (! is_string($name) || mb_trim($name) === '') {
            throw new Exception('Custom condition name is required.');
        }

        if (! is_string($type) || mb_trim($type) === '') {
            throw new Exception('Custom condition type is required.');
        }

        if (! is_string($target) || mb_trim($target) === '') {
            throw new Exception('Custom condition target is required.');
        }

        if (mb_strlen($name) > $limits->maxStringLength || mb_strlen($type) > $limits->maxStringLength) {
            throw new Exception("Custom condition name and type cannot exceed {$limits->maxStringLength} characters.");
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new Exception('Custom condition value must be a string, integer, or float.');
        }

        if (is_string($value) && mb_trim($value) === '') {
            throw new Exception('Custom condition value cannot be empty.');
        }

        $order = $data['order'] ?? 0;

        if (! is_int($order) && ! (is_string($order) && is_numeric($order))) {
            throw new Exception('Custom condition order must be an integer.');
        }

        return [
            'name' => $name,
            'type' => $type,
            'target' => $target,
            'value' => $value,
            'order' => (int) $order,
        ];
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
