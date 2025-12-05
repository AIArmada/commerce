<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Enums;

enum PolicyCombiningAlgorithm: string
{
    case DenyOverrides = 'deny_overrides';
    case PermitOverrides = 'permit_overrides';
    case FirstApplicable = 'first_applicable';
    case OnlyOneApplicable = 'only_one_applicable';
    case PermitUnlessDeny = 'permit_unless_deny';
    case DenyUnlessPermit = 'deny_unless_permit';

    public function label(): string
    {
        return match ($this) {
            self::DenyOverrides => 'Deny Overrides',
            self::PermitOverrides => 'Permit Overrides',
            self::FirstApplicable => 'First Applicable',
            self::OnlyOneApplicable => 'Only One Applicable',
            self::PermitUnlessDeny => 'Permit Unless Deny',
            self::DenyUnlessPermit => 'Deny Unless Permit',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DenyOverrides => 'If any policy denies, the result is deny. Most restrictive approach.',
            self::PermitOverrides => 'If any policy permits, the result is permit. Most permissive approach.',
            self::FirstApplicable => 'First applicable policy determines the result. Order matters.',
            self::OnlyOneApplicable => 'Exactly one policy must be applicable, otherwise indeterminate.',
            self::PermitUnlessDeny => 'Permit by default unless a deny is found.',
            self::DenyUnlessPermit => 'Deny by default unless a permit is found.',
        };
    }

    /**
     * Combine multiple policy decisions into a final decision.
     *
     * @param  array<PolicyDecision>  $decisions
     */
    public function combine(array $decisions): PolicyDecision
    {
        if (empty($decisions)) {
            return $this->defaultDecision();
        }

        return match ($this) {
            self::DenyOverrides => $this->combineWithDenyOverrides($decisions),
            self::PermitOverrides => $this->combineWithPermitOverrides($decisions),
            self::FirstApplicable => $this->combineWithFirstApplicable($decisions),
            self::OnlyOneApplicable => $this->combineWithOnlyOneApplicable($decisions),
            self::PermitUnlessDeny => $this->combineWithPermitUnlessDeny($decisions),
            self::DenyUnlessPermit => $this->combineWithDenyUnlessPermit($decisions),
        };
    }

    public function defaultDecision(): PolicyDecision
    {
        return match ($this) {
            self::DenyOverrides,
            self::DenyUnlessPermit,
            self::OnlyOneApplicable => PolicyDecision::Deny,
            self::PermitOverrides,
            self::PermitUnlessDeny => PolicyDecision::Permit,
            self::FirstApplicable => PolicyDecision::NotApplicable,
        };
    }

    /**
     * @param  array<PolicyDecision>  $decisions
     */
    private function combineWithDenyOverrides(array $decisions): PolicyDecision
    {
        $hasPermit = false;
        $hasIndeterminate = false;

        foreach ($decisions as $decision) {
            if ($decision === PolicyDecision::Deny) {
                return PolicyDecision::Deny;
            }
            if ($decision === PolicyDecision::Permit) {
                $hasPermit = true;
            }
            if ($decision === PolicyDecision::Indeterminate) {
                $hasIndeterminate = true;
            }
        }

        if ($hasPermit) {
            return PolicyDecision::Permit;
        }

        if ($hasIndeterminate) {
            return PolicyDecision::Indeterminate;
        }

        return PolicyDecision::NotApplicable;
    }

    /**
     * @param  array<PolicyDecision>  $decisions
     */
    private function combineWithPermitOverrides(array $decisions): PolicyDecision
    {
        $hasDeny = false;
        $hasIndeterminate = false;

        foreach ($decisions as $decision) {
            if ($decision === PolicyDecision::Permit) {
                return PolicyDecision::Permit;
            }
            if ($decision === PolicyDecision::Deny) {
                $hasDeny = true;
            }
            if ($decision === PolicyDecision::Indeterminate) {
                $hasIndeterminate = true;
            }
        }

        if ($hasDeny) {
            return PolicyDecision::Deny;
        }

        if ($hasIndeterminate) {
            return PolicyDecision::Indeterminate;
        }

        return PolicyDecision::NotApplicable;
    }

    /**
     * @param  array<PolicyDecision>  $decisions
     */
    private function combineWithFirstApplicable(array $decisions): PolicyDecision
    {
        foreach ($decisions as $decision) {
            if ($decision->isConclusive()) {
                return $decision;
            }
        }

        return PolicyDecision::NotApplicable;
    }

    /**
     * @param  array<PolicyDecision>  $decisions
     */
    private function combineWithOnlyOneApplicable(array $decisions): PolicyDecision
    {
        $applicableDecisions = array_filter(
            $decisions,
            fn (PolicyDecision $d) => $d->isConclusive()
        );

        if (count($applicableDecisions) === 1) {
            return reset($applicableDecisions);
        }

        if (count($applicableDecisions) > 1) {
            return PolicyDecision::Indeterminate;
        }

        return PolicyDecision::NotApplicable;
    }

    /**
     * @param  array<PolicyDecision>  $decisions
     */
    private function combineWithPermitUnlessDeny(array $decisions): PolicyDecision
    {
        foreach ($decisions as $decision) {
            if ($decision === PolicyDecision::Deny) {
                return PolicyDecision::Deny;
            }
        }

        return PolicyDecision::Permit;
    }

    /**
     * @param  array<PolicyDecision>  $decisions
     */
    private function combineWithDenyUnlessPermit(array $decisions): PolicyDecision
    {
        foreach ($decisions as $decision) {
            if ($decision === PolicyDecision::Permit) {
                return PolicyDecision::Permit;
            }
        }

        return PolicyDecision::Deny;
    }
}
