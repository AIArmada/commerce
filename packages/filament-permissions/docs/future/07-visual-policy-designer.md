# Future: Visual Policy Designer

> **Drag-and-drop ABAC policy builder for non-technical administrators**

## Overview

Our existing ABAC Policy Engine is powerful but requires code to configure. The Visual Policy Designer provides a Filament-native UI for building complex access control policies without writing PHP.

## Key Features

### 1. Policy Canvas

A visual interface where administrators can:
- Create policies with drag-and-drop conditions
- Define subjects, actions, and resources
- Set combining algorithms visually
- Preview policy effects before saving
- Simulate policy evaluation against test users

### 2. Condition Builder

```
┌─────────────────────────────────────────────────────────────────┐
│ Policy: Order Management                                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  IF                                                              │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Subject                                                   │   │
│  │  ┌────────────────┐  ┌────────────────┐                  │   │
│  │  │ Role is any of │  │ Manager, Admin │ ✕               │   │
│  │  └────────────────┘  └────────────────┘                  │   │
│  │                                                           │   │
│  │  [+ Add Condition]                                       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  AND                                                             │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Context                                                   │   │
│  │  ┌────────────────┐  ┌────────────────┐  ┌────────────┐ │   │
│  │  │ Order amount   │  │ is less than   │  │ 10000      │ │   │
│  │  └────────────────┘  └────────────────┘  └────────────┘ │   │
│  │                                                           │   │
│  │  [+ Add Condition]                                       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  THEN                                                            │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Effect                                                    │   │
│  │  ┌────────────────┐                                      │   │
│  │  │ ● Allow        │  ○ Deny                              │   │
│  │  └────────────────┘                                      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  [Test Policy]  [Preview JSON]  [Save Draft]  [Publish]         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3. Implementation

```php
namespace AIArmada\FilamentPermissions\Pages;

class PolicyDesignerPage extends Page
{
    protected static string $view = 'filament-permissions::pages.policy-designer';
    
    public ?string $policyId = null;
    public array $conditions = [];
    public string $effect = 'allow';
    public string $combiningAlgorithm = 'first_applicable';
    public array $subjects = [];
    public array $resources = [];
    public array $actions = [];
    
    protected array $conditionTemplates = [
        'role' => [
            'label' => 'User has role',
            'operators' => ['is', 'is_any_of', 'is_not', 'is_none_of'],
            'valueType' => 'role_select',
        ],
        'permission' => [
            'label' => 'User has permission',
            'operators' => ['has', 'has_any_of', 'has_all_of'],
            'valueType' => 'permission_select',
        ],
        'team' => [
            'label' => 'User is in team',
            'operators' => ['is_member', 'is_owner', 'is_admin'],
            'valueType' => 'team_select',
        ],
        'time' => [
            'label' => 'Current time',
            'operators' => ['is_between', 'is_after', 'is_before', 'is_day_of_week'],
            'valueType' => 'time_picker',
        ],
        'ip_address' => [
            'label' => 'Request IP',
            'operators' => ['is_in_range', 'is_not_in_range', 'matches'],
            'valueType' => 'text',
        ],
        'resource_attribute' => [
            'label' => 'Resource attribute',
            'operators' => ['equals', 'not_equals', 'greater_than', 'less_than', 'in', 'contains'],
            'valueType' => 'attribute_builder',
        ],
        'ownership' => [
            'label' => 'User owns resource',
            'operators' => ['is_owner', 'is_creator', 'is_team_member'],
            'valueType' => 'none',
        ],
    ];
    
    public function addCondition(string $type, string $group): void
    {
        $this->conditions[$group][] = [
            'type' => $type,
            'operator' => $this->conditionTemplates[$type]['operators'][0],
            'value' => null,
            'id' => Str::uuid()->toString(),
        ];
    }
    
    public function removeCondition(string $id): void
    {
        foreach ($this->conditions as $group => $conditions) {
            $this->conditions[$group] = array_filter(
                $conditions,
                fn ($c) => $c['id'] !== $id
            );
        }
    }
    
    public function moveCondition(string $id, string $direction): void
    {
        // Drag-and-drop reordering
    }
    
    public function testPolicy(): array
    {
        $testUser = $this->getTestUser();
        $testResource = $this->getTestResource();
        
        $policy = $this->compilePolicy();
        
        $result = app(PolicyEngine::class)->evaluate(
            $policy->target,
            $this->buildContext($testUser, $testResource)
        );
        
        return [
            'decision' => $result->getDecision()->value,
            'reason' => $result->getReason(),
            'evaluated_conditions' => $result->getEvaluatedConditions(),
            'trace' => $result->getTrace(),
        ];
    }
    
    public function compilePolicy(): AccessPolicy
    {
        return AccessPolicy::create([
            'name' => $this->policyName,
            'target' => $this->actions,
            'effect' => PolicyEffect::from($this->effect),
            'conditions' => $this->compileConditions(),
            'combining_algorithm' => PolicyCombiningAlgorithm::from($this->combiningAlgorithm),
            'priority' => $this->priority,
            'metadata' => [
                'created_via' => 'visual_designer',
                'version' => 1,
            ],
        ]);
    }
    
    protected function compileConditions(): array
    {
        $compiled = [];
        
        foreach ($this->conditions as $group => $conditions) {
            $groupConditions = [];
            
            foreach ($conditions as $condition) {
                $groupConditions[] = PolicyCondition::{$condition['type']}(
                    ConditionOperator::from($condition['operator']),
                    $condition['value']
                );
            }
            
            $compiled[$group] = $groupConditions;
        }
        
        return $compiled;
    }
    
    public function exportAsCode(): string
    {
        $policy = $this->compilePolicy();
        
        return <<<PHP
        use AIArmada\\FilamentPermissions\\Services\\PolicyBuilder;
        
        PolicyBuilder::make('{$policy->name}')
            ->action('{$this->actions[0]}')
            ->effect(PolicyEffect::{$this->effect})
            {$this->generateConditionsCode()}
            ->save();
        PHP;
    }
}
```

### 4. Blade View

```blade
{{-- resources/views/pages/policy-designer.blade.php --}}
<x-filament::page>
    <div class="grid grid-cols-3 gap-6">
        {{-- Condition Toolbox --}}
        <div class="col-span-1">
            <x-filament::section>
                <x-slot name="heading">Condition Types</x-slot>
                
                <div class="space-y-2" wire:sortable="reorderConditions">
                    @foreach($conditionTemplates as $type => $template)
                        <div 
                            class="p-3 bg-gray-50 dark:bg-gray-800 rounded cursor-move hover:bg-gray-100"
                            draggable="true"
                            wire:click="addCondition('{{ $type }}', 'subject')"
                        >
                            <span class="font-medium">{{ $template['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
        
        {{-- Policy Canvas --}}
        <div class="col-span-2">
            <x-filament::section>
                <x-slot name="heading">
                    Policy: {{ $policyName ?? 'New Policy' }}
                </x-slot>
                
                {{-- Subject Conditions --}}
                <div class="mb-4">
                    <h4 class="font-semibold mb-2">IF (Subject)</h4>
                    <div class="space-y-2 p-4 border rounded" wire:sortable="reorderSubjectConditions">
                        @foreach($conditions['subject'] ?? [] as $condition)
                            <x-filament-permissions::condition-row 
                                :condition="$condition"
                                :templates="$conditionTemplates"
                            />
                        @endforeach
                        @if(empty($conditions['subject']))
                            <div class="text-gray-400 text-center py-4">
                                Drag conditions here
                            </div>
                        @endif
                    </div>
                </div>
                
                {{-- Context Conditions --}}
                <div class="mb-4">
                    <div class="text-center text-gray-500 my-2">AND</div>
                    <h4 class="font-semibold mb-2">Context</h4>
                    <div class="space-y-2 p-4 border rounded" wire:sortable="reorderContextConditions">
                        @foreach($conditions['context'] ?? [] as $condition)
                            <x-filament-permissions::condition-row 
                                :condition="$condition"
                                :templates="$conditionTemplates"
                            />
                        @endforeach
                    </div>
                </div>
                
                {{-- Effect --}}
                <div class="mb-4">
                    <div class="text-center text-gray-500 my-2">THEN</div>
                    <h4 class="font-semibold mb-2">Effect</h4>
                    <div class="flex gap-4 p-4 border rounded">
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:model="effect" value="allow" class="text-primary-600">
                            <span class="text-green-600 font-medium">Allow</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:model="effect" value="deny" class="text-primary-600">
                            <span class="text-red-600 font-medium">Deny</span>
                        </label>
                    </div>
                </div>
                
                {{-- Actions --}}
                <div class="flex gap-2 mt-6">
                    <x-filament::button wire:click="testPolicy" color="gray">
                        Test Policy
                    </x-filament::button>
                    <x-filament::button wire:click="previewJson" color="gray">
                        Preview JSON
                    </x-filament::button>
                    <x-filament::button wire:click="exportAsCode" color="gray">
                        Export as Code
                    </x-filament::button>
                    <x-filament::button wire:click="save" color="primary">
                        Save Policy
                    </x-filament::button>
                </div>
            </x-filament::section>
            
            {{-- Test Results Panel --}}
            @if($testResults)
                <x-filament::section class="mt-4">
                    <x-slot name="heading">Test Results</x-slot>
                    
                    <div class="p-4 rounded {{ $testResults['decision'] === 'permit' ? 'bg-green-50' : 'bg-red-50' }}">
                        <p class="font-bold {{ $testResults['decision'] === 'permit' ? 'text-green-700' : 'text-red-700' }}">
                            Decision: {{ strtoupper($testResults['decision']) }}
                        </p>
                        <p class="text-sm text-gray-600 mt-2">{{ $testResults['reason'] }}</p>
                        
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm">Evaluation Trace</summary>
                            <pre class="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto">{{ json_encode($testResults['trace'], JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament::page>
```

### 5. Policy Templates

Pre-built policy templates for common scenarios:

```php
class PolicyTemplates
{
    public static function ownerOnly(): array
    {
        return [
            'name' => 'Owner Only Access',
            'description' => 'Allow access only to resource owner',
            'conditions' => [
                'subject' => [
                    ['type' => 'ownership', 'operator' => 'is_owner', 'value' => null],
                ],
            ],
            'effect' => 'allow',
        ];
    }
    
    public static function businessHours(): array
    {
        return [
            'name' => 'Business Hours Only',
            'description' => 'Allow access only during business hours',
            'conditions' => [
                'context' => [
                    ['type' => 'time', 'operator' => 'is_between', 'value' => ['09:00', '17:00']],
                    ['type' => 'time', 'operator' => 'is_day_of_week', 'value' => [1, 2, 3, 4, 5]],
                ],
            ],
            'effect' => 'allow',
        ];
    }
    
    public static function approvalRequired(): array
    {
        return [
            'name' => 'Approval Required for High Value',
            'description' => 'Require manager approval for high-value actions',
            'conditions' => [
                'subject' => [
                    ['type' => 'role', 'operator' => 'is_none_of', 'value' => ['Manager', 'Admin']],
                ],
                'context' => [
                    ['type' => 'resource_attribute', 'operator' => 'greater_than', 'value' => ['amount', 10000]],
                ],
            ],
            'effect' => 'deny',
        ];
    }
    
    public static function ipWhitelist(): array
    {
        return [
            'name' => 'Office IP Only',
            'description' => 'Allow access only from office IP ranges',
            'conditions' => [
                'context' => [
                    ['type' => 'ip_address', 'operator' => 'is_in_range', 'value' => ['192.168.1.0/24', '10.0.0.0/8']],
                ],
            ],
            'effect' => 'allow',
        ];
    }
}
```

## Shield Comparison

This feature has **no equivalent in Shield**. Shield focuses on static role-based permissions; our Visual Policy Designer enables dynamic, context-aware authorization that can be managed by non-developers.

## Benefits

1. **No-code policy creation** — Business users can define access rules
2. **Real-time testing** — Preview policy effects before deployment
3. **Code export** — Generate PHP code for version control
4. **Templates** — Quick start with common patterns
5. **Audit trail** — Track who created/modified policies
