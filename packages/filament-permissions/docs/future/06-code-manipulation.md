# Future: Code Manipulation Engine

> **Intelligently modify PHP files — inspired by Shield's Stringer class**

## Overview

Shield's `Stringer` class is a powerful tool for programmatically modifying PHP files. We enhance this pattern with AST-based manipulation and safer transformations.

## Shield's Stringer Analysis

Shield's Stringer provides:
- `prepend()` / `append()` — Add content before/after a needle
- `replace()` — Replace content
- `deleteLine()` — Remove a line
- `findChainedBlock()` — Find method chains
- `appendBlock()` — Add content after method body

**Limitations:**
- String-based (not AST)
- No rollback capability
- Limited context awareness
- Manual indentation handling

## Our Enhanced Implementation

### 1. CodeManipulator Service

```php
namespace AIArmada\FilamentPermissions\Services;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

class CodeManipulator
{
    protected Parser $parser;
    protected PrettyPrinter\Standard $printer;
    protected string $content;
    protected string $filePath;
    protected array $history = [];
    
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->content = file_get_contents($filePath);
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->printer = new PrettyPrinter\Standard([
            'shortArraySyntax' => true,
        ]);
    }
    
    public static function for(string $filePath): static
    {
        return new static($filePath);
    }
    
    /**
     * Add a use statement if not already present.
     */
    public function addUse(string $className): static
    {
        $this->saveState();
        
        if ($this->containsUse($className)) {
            return $this;
        }
        
        $ast = $this->parse();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AddUseStatementVisitor($className));
        $modified = $traverser->traverse($ast);
        
        $this->content = $this->printer->prettyPrintFile($modified);
        
        return $this;
    }
    
    /**
     * Add a trait to a class.
     */
    public function addTrait(string $traitName): static
    {
        $this->saveState();
        
        if ($this->containsTrait($traitName)) {
            return $this;
        }
        
        $ast = $this->parse();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AddTraitVisitor($traitName));
        $modified = $traverser->traverse($ast);
        
        $this->content = $this->printer->prettyPrintFile($modified);
        
        return $this;
    }
    
    /**
     * Add or update a class property.
     */
    public function setProperty(string $name, mixed $value, string $visibility = 'protected'): static
    {
        $this->saveState();
        
        $ast = $this->parse();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new SetPropertyVisitor($name, $value, $visibility));
        $modified = $traverser->traverse($ast);
        
        $this->content = $this->printer->prettyPrintFile($modified);
        
        return $this;
    }
    
    /**
     * Add a method to a class.
     */
    public function addMethod(string $methodCode): static
    {
        $this->saveState();
        
        // Parse the method code
        $methodAst = $this->parser->parse("<?php class _ { {$methodCode} }");
        $method = $methodAst[0]->stmts[0];
        
        $ast = $this->parse();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AddMethodVisitor($method));
        $modified = $traverser->traverse($ast);
        
        $this->content = $this->printer->prettyPrintFile($modified);
        
        return $this;
    }
    
    /**
     * Modify array in configuration or property.
     */
    public function appendToArray(string $propertyName, mixed $item): static
    {
        $this->saveState();
        
        $ast = $this->parse();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AppendToArrayVisitor($propertyName, $item));
        $modified = $traverser->traverse($ast);
        
        $this->content = $this->printer->prettyPrintFile($modified);
        
        return $this;
    }
    
    /**
     * Add content after a specific line pattern (Stringer-compatible).
     */
    public function appendAfter(string $needle, string $content): static
    {
        $this->saveState();
        
        $lines = explode("\n", $this->content);
        $newLines = [];
        
        foreach ($lines as $line) {
            $newLines[] = $line;
            if (str_contains($line, $needle)) {
                $indent = $this->detectIndent($line);
                $newLines[] = $indent . trim($content);
            }
        }
        
        $this->content = implode("\n", $newLines);
        
        return $this;
    }
    
    /**
     * Add content before a specific line pattern.
     */
    public function prependBefore(string $needle, string $content): static
    {
        $this->saveState();
        
        $lines = explode("\n", $this->content);
        $newLines = [];
        
        foreach ($lines as $line) {
            if (str_contains($line, $needle)) {
                $indent = $this->detectIndent($line);
                $newLines[] = $indent . trim($content);
            }
            $newLines[] = $line;
        }
        
        $this->content = implode("\n", $newLines);
        
        return $this;
    }
    
    /**
     * Wrap a method body with additional logic.
     */
    public function wrapMethod(string $methodName, string $beforeCode, ?string $afterCode = null): static
    {
        $this->saveState();
        
        $ast = $this->parse();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new WrapMethodVisitor($methodName, $beforeCode, $afterCode));
        $modified = $traverser->traverse($ast);
        
        $this->content = $this->printer->prettyPrintFile($modified);
        
        return $this;
    }
    
    /**
     * Check if content contains pattern.
     */
    public function contains(string $pattern): bool
    {
        return str_contains($this->content, $pattern);
    }
    
    /**
     * Check if class uses trait.
     */
    public function containsTrait(string $traitName): bool
    {
        $shortName = class_basename($traitName);
        return str_contains($this->content, "use {$shortName}") ||
               str_contains($this->content, "use \\{$traitName}");
    }
    
    /**
     * Check if file has use statement.
     */
    public function containsUse(string $className): bool
    {
        return str_contains($this->content, "use {$className};");
    }
    
    /**
     * Undo last modification.
     */
    public function undo(): static
    {
        if (!empty($this->history)) {
            $this->content = array_pop($this->history);
        }
        return $this;
    }
    
    /**
     * Save the file.
     */
    public function save(): bool
    {
        return (bool) file_put_contents($this->filePath, $this->content);
    }
    
    /**
     * Save to a new location.
     */
    public function saveAs(string $path): bool
    {
        return (bool) file_put_contents($path, $this->content);
    }
    
    /**
     * Get current content.
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * Preview changes as diff.
     */
    public function diff(): string
    {
        $original = file_get_contents($this->filePath);
        
        return $this->generateDiff($original, $this->content);
    }
    
    protected function saveState(): void
    {
        $this->history[] = $this->content;
    }
    
    protected function parse(): array
    {
        return $this->parser->parse($this->content);
    }
    
    protected function detectIndent(string $line): string
    {
        preg_match('/^(\s*)/', $line, $matches);
        return $matches[1] ?? '';
    }
}
```

### 2. AST Visitors

```php
namespace AIArmada\FilamentPermissions\Services\CodeManipulation;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class AddTraitVisitor extends NodeVisitorAbstract
{
    public function __construct(protected string $traitName) {}
    
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            // Check if trait already exists
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\TraitUse) {
                    foreach ($stmt->traits as $trait) {
                        if ($trait->toString() === $this->traitName) {
                            return null; // Already has trait
                        }
                    }
                }
            }
            
            // Find existing trait use or create new one
            $traitUse = null;
            foreach ($node->stmts as $index => $stmt) {
                if ($stmt instanceof Node\Stmt\TraitUse) {
                    $traitUse = $stmt;
                    $traitUse->traits[] = new Node\Name($this->traitName);
                    return null;
                }
            }
            
            // No existing trait use, create one
            $newTraitUse = new Node\Stmt\TraitUse([
                new Node\Name($this->traitName)
            ]);
            
            // Insert after class opening
            array_unshift($node->stmts, $newTraitUse);
        }
        
        return null;
    }
}

class AddMethodVisitor extends NodeVisitorAbstract
{
    public function __construct(protected Node\Stmt\ClassMethod $method) {}
    
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            // Check if method already exists
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\ClassMethod 
                    && $stmt->name->toString() === $this->method->name->toString()) {
                    return null; // Method already exists
                }
            }
            
            // Add method at end of class
            $node->stmts[] = $this->method;
        }
        
        return null;
    }
}

class SetPropertyVisitor extends NodeVisitorAbstract
{
    public function __construct(
        protected string $name,
        protected mixed $value,
        protected string $visibility = 'protected'
    ) {}
    
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            // Check if property exists
            foreach ($node->stmts as $index => $stmt) {
                if ($stmt instanceof Node\Stmt\Property) {
                    foreach ($stmt->props as $prop) {
                        if ($prop->name->toString() === $this->name) {
                            // Update existing property
                            $prop->default = $this->valueToNode($this->value);
                            return null;
                        }
                    }
                }
            }
            
            // Create new property
            $flags = match ($this->visibility) {
                'public' => Node\Stmt\Class_::MODIFIER_PUBLIC,
                'protected' => Node\Stmt\Class_::MODIFIER_PROTECTED,
                'private' => Node\Stmt\Class_::MODIFIER_PRIVATE,
            };
            
            $property = new Node\Stmt\Property(
                $flags,
                [new Node\PropertyItem(
                    new Node\VarLikeIdentifier($this->name),
                    $this->valueToNode($this->value)
                )]
            );
            
            // Insert after traits and before methods
            $insertIndex = 0;
            foreach ($node->stmts as $index => $stmt) {
                if ($stmt instanceof Node\Stmt\TraitUse || $stmt instanceof Node\Stmt\Property) {
                    $insertIndex = $index + 1;
                } elseif ($stmt instanceof Node\Stmt\ClassMethod) {
                    break;
                }
            }
            
            array_splice($node->stmts, $insertIndex, 0, [$property]);
        }
        
        return null;
    }
    
    protected function valueToNode(mixed $value): Node\Expr
    {
        return match (true) {
            is_string($value) => new Node\Scalar\String_($value),
            is_int($value) => new Node\Scalar\Int_($value),
            is_float($value) => new Node\Scalar\Float_($value),
            is_bool($value) => new Node\Expr\ConstFetch(new Node\Name($value ? 'true' : 'false')),
            is_null($value) => new Node\Expr\ConstFetch(new Node\Name('null')),
            is_array($value) => $this->arrayToNode($value),
            default => throw new \InvalidArgumentException('Unsupported value type'),
        };
    }
    
    protected function arrayToNode(array $array): Node\Expr\Array_
    {
        $items = [];
        foreach ($array as $key => $value) {
            $items[] = new Node\ArrayItem(
                $this->valueToNode($value),
                is_int($key) ? null : new Node\Scalar\String_($key)
            );
        }
        return new Node\Expr\Array_($items);
    }
}
```

### 3. CLI Integration

```php
#[AsCommand(name: 'permissions:install-trait')]
class InstallTraitCommand extends Command
{
    public $signature = 'permissions:install-trait
        {--page=* : Pages to add HasPagePermissions trait}
        {--widget=* : Widgets to add HasWidgetPermissions trait}
        {--resource=* : Resources to add HasResourcePermissions trait}
        {--user : Add HasPanelPermissions to User model}
        {--all : Apply to all discovered entities}
        {--dry-run : Preview changes without applying}';
    
    public function handle(EntityDiscoveryService $discovery): int
    {
        $changes = [];
        
        if ($this->option('user')) {
            $changes[] = $this->installUserTrait();
        }
        
        if ($this->option('all')) {
            $changes = array_merge($changes, $this->installAllTraits($discovery));
        } else {
            foreach ($this->option('page') as $page) {
                $changes[] = $this->installPageTrait($page);
            }
            foreach ($this->option('widget') as $widget) {
                $changes[] = $this->installWidgetTrait($widget);
            }
            foreach ($this->option('resource') as $resource) {
                $changes[] = $this->installResourceTrait($resource);
            }
        }
        
        if ($this->option('dry-run')) {
            foreach ($changes as $change) {
                $this->info("Would modify: {$change['file']}");
                $this->line($change['diff']);
            }
            return Command::SUCCESS;
        }
        
        foreach ($changes as $change) {
            $change['manipulator']->save();
            $this->line("✓ Modified: {$change['file']}");
        }
        
        $this->info("✅ Installed traits on " . count($changes) . " files");
        
        return Command::SUCCESS;
    }
    
    protected function installUserTrait(): array
    {
        $userModelPath = $this->getUserModelPath();
        
        $manipulator = CodeManipulator::for($userModelPath)
            ->addUse('AIArmada\\FilamentPermissions\\Traits\\HasPanelPermissions')
            ->addTrait('HasPanelPermissions');
        
        return [
            'file' => $userModelPath,
            'manipulator' => $manipulator,
            'diff' => $manipulator->diff(),
        ];
    }
    
    protected function installPageTrait(string $pageClass): array
    {
        $reflection = new \ReflectionClass($pageClass);
        $filePath = $reflection->getFileName();
        
        $manipulator = CodeManipulator::for($filePath)
            ->addUse('AIArmada\\FilamentPermissions\\Traits\\HasPagePermissions')
            ->addTrait('HasPagePermissions');
        
        return [
            'file' => $filePath,
            'manipulator' => $manipulator,
            'diff' => $manipulator->diff(),
        ];
    }
}
```

### 4. Usage Examples

```php
// Add trait to class
CodeManipulator::for(app_path('Filament/Pages/Dashboard.php'))
    ->addUse('AIArmada\FilamentPermissions\Traits\HasPagePermissions')
    ->addTrait('HasPagePermissions')
    ->save();

// Add property
CodeManipulator::for(app_path('Models/User.php'))
    ->setProperty('defaultRoles', ['panel_user'])
    ->save();

// Add method
CodeManipulator::for(app_path('Filament/Resources/PostResource.php'))
    ->addMethod('
        public static function getCustomAbilities(): array
        {
            return ["approve", "publish", "archive"];
        }
    ')
    ->save();

// Append to array property
CodeManipulator::for(config_path('filament-permissions.php'))
    ->appendToArray('sync.permissions', 'post.approve')
    ->save();

// Wrap method with additional logic
CodeManipulator::for(app_path('Filament/Pages/Settings.php'))
    ->wrapMethod('mount', 
        beforeCode: 'Permissions::audit()->log("settings_accessed");',
        afterCode: null
    )
    ->save();

// Preview changes
$diff = CodeManipulator::for(app_path('Models/User.php'))
    ->addTrait('HasPanelPermissions')
    ->diff();
echo $diff;

// Undo last change
$manipulator = CodeManipulator::for($file);
$manipulator->addTrait('SomeTrait');
$manipulator->undo(); // Removes the trait
```

## Comparison with Shield's Stringer

| Feature | Shield Stringer | Our CodeManipulator |
|---------|-----------------|---------------------|
| **Approach** | String-based | AST + String hybrid |
| **Indent handling** | Manual | Automatic |
| **Rollback** | ❌ | ✅ History-based |
| **Diff preview** | ❌ | ✅ Before/after diff |
| **AST awareness** | ❌ | ✅ PhpParser-based |
| **Trait detection** | ❌ | ✅ Duplicate prevention |
| **Method wrapping** | ❌ | ✅ |
| **Array manipulation** | ❌ | ✅ |
| **Dry-run mode** | ❌ | ✅ |
| **Cross-platform** | ✅ | ✅ |

## Why Hybrid Approach?

1. **AST for structure** — Safely add traits, methods, properties with proper positioning
2. **String for content** — Simple pattern matching for quick modifications
3. **Best of both** — Powerful + familiar API
