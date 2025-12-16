<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\CodeManipulator;

test('code manipulator can be instantiated with file', function (): void {
    $testFile = storage_path('test-class.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator)->toBeInstanceOf(CodeManipulator::class);

    unlink($testFile);
});

test('code manipulator can check for trait', function (): void {
    $testFile = storage_path('test-class-2.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

use App\Traits\SomeTrait;

class TestClass
{
    use SomeTrait;

    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator->containsTrait('SomeTrait'))->toBeTrue()
        ->and($manipulator->containsTrait('OtherTrait'))->toBeFalse();

    unlink($testFile);
});

test('code manipulator can add trait', function (): void {
    $testFile = storage_path('test-class-3.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addTrait('App\\Traits\\NewTrait');

    $preview = $manipulator->preview();

    // Check for trait usage in class body (use NewTrait;)
    expect($preview)->toContain('use NewTrait;');

    unlink($testFile);
});

test('code manipulator can generate diff', function (): void {
    $testFile = storage_path('test-class-4.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addTrait('App\\Traits\\NewTrait');

    $diff = $manipulator->diff();

    expect($diff)->toContain('+');

    unlink($testFile);
});

test('code manipulator can add use statement', function (): void {
    $testFile = storage_path('test-class-5.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addUse('App\\Services\\SomeService');

    $preview = $manipulator->preview();

    expect($preview)->toContain('use App\\Services\\SomeService;');

    unlink($testFile);
});

test('code manipulator does not duplicate use statements', function (): void {
    $testFile = storage_path('test-class-6.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

use App\Services\SomeService;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addUse('App\\Services\\SomeService');

    $preview = $manipulator->preview();
    $count = mb_substr_count($preview, 'use App\\Services\\SomeService;');

    expect($count)->toBe(1);

    unlink($testFile);
});

test('code manipulator contains method check', function (): void {
    $testFile = storage_path('test-class-7.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    public function existingMethod()
    {
        return true;
    }
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator->containsMethod('existingMethod'))->toBeTrue()
        ->and($manipulator->containsMethod('missingMethod'))->toBeFalse();

    unlink($testFile);
});

test('code manipulator can undo changes', function (): void {
    $testFile = storage_path('test-class-8.php');
    $original = <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP;
    file_put_contents($testFile, $original);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addUse('App\\Services\\First');
    $manipulator->addUse('App\\Services\\Second');

    // Undo second addition
    $manipulator->undo();
    $preview = $manipulator->preview();

    expect($preview)->toContain('App\\Services\\First')
        ->and($preview)->not->toContain('App\\Services\\Second');

    unlink($testFile);
});

test('code manipulator can set property value', function (): void {
    $testFile = storage_path('test-class-9.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'original';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('name', 'updated');

    $preview = $manipulator->preview();

    expect($preview)->toContain("'updated'");

    unlink($testFile);
});

test('code manipulator can add new property', function (): void {
    $testFile = storage_path('test-class-10.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('newProp', 'value');

    $preview = $manipulator->preview();

    expect($preview)->toContain('$newProp');

    unlink($testFile);
});

test('code manipulator can add method', function (): void {
    $testFile = storage_path('test-class-11.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addMethod('newMethod', '        return true;');

    $preview = $manipulator->preview();

    expect($preview)->toContain('function newMethod()');
    expect($preview)->toContain('return true;');

    unlink($testFile);
});

test('code manipulator does not add duplicate method', function (): void {
    $testFile = storage_path('test-class-12.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    public function existingMethod()
    {
        return 'original';
    }
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addMethod('existingMethod', '        return "duplicate";');

    $preview = $manipulator->preview();

    expect($preview)->toContain('original');
    expect($preview)->not->toContain('duplicate');

    unlink($testFile);
});

test('code manipulator can add static method', function (): void {
    $testFile = storage_path('test-class-13.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addMethod('staticMethod', '        return null;', 'public', true);

    $preview = $manipulator->preview();

    expect($preview)->toContain('public static function staticMethod()');

    unlink($testFile);
});

test('code manipulator can append to array property', function (): void {
    $testFile = storage_path('test-class-14.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected array $items = [];
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->appendToArray('items', 'key1', 'value1');

    $preview = $manipulator->preview();

    expect($preview)->toContain("'key1' => 'value1'");

    unlink($testFile);
});

test('code manipulator can save changes', function (): void {
    $testFile = storage_path('test-class-15.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'original';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('name', 'saved');

    $result = $manipulator->save();

    expect($result)->toBeTrue();
    expect(file_get_contents($testFile))->toContain("'saved'");

    unlink($testFile);
});

test('code manipulator save returns false when no changes', function (): void {
    $testFile = storage_path('test-class-16.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $result = $manipulator->save();

    expect($result)->toBeFalse();

    unlink($testFile);
});

test('code manipulator containsUse check', function (): void {
    $testFile = storage_path('test-class-17.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

use App\Services\ExistingService;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator->containsUse('App\\Services\\ExistingService'))->toBeTrue();
    expect($manipulator->containsUse('App\\Services\\NonExistent'))->toBeFalse();

    unlink($testFile);
});

test('code manipulator undo does nothing when no history', function (): void {
    $testFile = storage_path('test-class-18.php');
    $original = <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP;
    file_put_contents($testFile, $original);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->undo();

    // Should still have original content
    expect($manipulator->preview())->toBe($original);

    unlink($testFile);
});

test('code manipulator handles boolean values', function (): void {
    $testFile = storage_path('test-class-19.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected bool $flag = false;
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('flag', true);

    $preview = $manipulator->preview();

    expect($preview)->toContain('true');

    unlink($testFile);
});

test('code manipulator handles null values', function (): void {
    $testFile = storage_path('test-class-20.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected ?string $name = 'value';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('name', null);

    $preview = $manipulator->preview();

    expect($preview)->toContain('null');

    unlink($testFile);
});

test('code manipulator handles array values', function (): void {
    $testFile = storage_path('test-class-21.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected array $items = [];
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('items', ['a', 'b', 'c']);

    $preview = $manipulator->preview();

    expect($preview)->toContain("['a', 'b', 'c']");

    unlink($testFile);
});

test('code manipulator handles associative array values', function (): void {
    $testFile = storage_path('test-class-22.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected array $config = [];
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->setProperty('config', ['key' => 'value', 'num' => 42]);

    $preview = $manipulator->preview();

    expect($preview)->toContain("'key' => 'value'");
    expect($preview)->toContain("'num' => 42");

    unlink($testFile);
});

test('code manipulator diff returns empty when no changes', function (): void {
    $testFile = storage_path('test-class-23.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $diff = $manipulator->diff();

    expect($diff)->toBe('');

    unlink($testFile);
});

test('code manipulator supports fluent chaining', function (): void {
    $testFile = storage_path('test-class-24.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $result = CodeManipulator::make($testFile)
        ->addUse('App\\Traits\\OneTrait')
        ->setProperty('name', 'chained');

    expect($result)->toBeInstanceOf(CodeManipulator::class);

    unlink($testFile);
});
