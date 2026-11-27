<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Features\Conditions\Models\Condition;
use AIArmada\Cart\Features\Conditions\Queries\ActiveGlobalConditions;
use AIArmada\Cart\Runtime\CartInstanceManager;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

final readonly class ReconcileGlobalConditions
{
    private const APPLYING_CONTEXT_KEY = 'cart.applying_global_conditions';

    public function __construct(
        private RulesFactoryInterface $rulesFactory,
        private CartInstanceManager $cartInstances,
    ) {}

    public function execute(Cart $cart): void
    {
        if (! config('cart.conditions.apply_global', true) || $this->isApplying()) {
            return;
        }

        Context::scope(function () use ($cart): void {
            $cart = $this->cartInstances->prepare($cart);

            $globalConditions = ActiveGlobalConditions::forCart($cart);
            ActiveGlobalConditions::removeInactive($cart, $globalConditions);

            foreach ($globalConditions->unique('name') as $condition) {
                $this->reconcileCondition($cart, $condition);
            }
        }, hidden: [self::APPLYING_CONTEXT_KEY => true]);
    }

    private function reconcileCondition(Cart $cart, Condition $condition): void
    {
        $name = $condition->name;
        $stored = $cart->getStoredConditions()->getCondition($name);
        $dynamic = $cart->getDynamicConditions()->getCondition($name);

        // A shared condition must never overwrite an unrelated user/provider
        // condition simply because their display identifiers collide.
        foreach ([$stored, $dynamic] as $existing) {
            if ($existing !== null && $existing->getAttribute('is_global') !== true) {
                return;
            }
        }

        $data = $condition->toConditionArray($name);
        $data['attributes']['display_name'] = $condition->display_name;
        $data['attributes']['description'] = $condition->description;
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $data['attributes']['definition_hash'] = $fingerprint;

        if ($condition->isDynamic()) {
            $factoryKeys = $condition->getRuleFactoryKeys();

            if ($factoryKeys === []) {
                if ($dynamic !== null) {
                    $cart->removeDynamicCondition($name);
                } elseif ($stored !== null) {
                    $cart->removeCondition($name);
                }

                return;
            }

            if ($dynamic?->getAttribute('definition_hash') === $fingerprint) {
                return;
            }

            $context = $condition->getRuleContext();
            $data['rules'] = $this->buildRuleCallables($factoryKeys, $context);
            $replacement = CartCondition::fromArray($data);

            if ($dynamic !== null) {
                $cart->removeDynamicCondition($name);
            } elseif ($stored !== null) {
                $cart->removeCondition($name);
            }

            $cart->registerDynamicCondition(
                $replacement,
                ruleFactoryKey: count($factoryKeys) === 1 ? $factoryKeys[0] : $factoryKeys,
                metadata: ['context' => $context],
            );

            return;
        }

        if ($dynamic !== null) {
            $cart->removeDynamicCondition($name);
            $stored = null;
        }

        if ($stored?->getAttribute('definition_hash') !== $fingerprint) {
            $data['rules'] = null;
            $cart->addCondition(CartCondition::fromArray($data));
        }
    }

    private function isApplying(): bool
    {
        return (bool) Context::getHidden(self::APPLYING_CONTEXT_KEY, false);
    }

    /**
     * @param  array<int, string>  $factoryKeys
     * @param  array<string, mixed>  $context
     * @return array<callable>
     */
    private function buildRuleCallables(array $factoryKeys, array $context): array
    {
        $rules = [];

        foreach ($factoryKeys as $factoryKey) {
            if (! $this->rulesFactory->canCreateRules($factoryKey)) {
                throw new InvalidArgumentException("Unsupported rule factory key [{$factoryKey}]");
            }

            $rules = array_merge(
                $rules,
                $this->rulesFactory->createRules($factoryKey, ['context' => $context])
            );
        }

        return $rules;
    }
}
