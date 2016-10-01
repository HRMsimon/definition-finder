<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;

// Hack is unaware of this
const int T_SELECT = 422;
const int T_ON = 415;

class NamingTest extends \PHPUnit_Framework_TestCase {
  public function testFunctionCalledSelect(): void {
    // 'select' is a T_SELECT, not a T_STRING
    $data = '<?hh function select() {}';

    // Check that it parses
    $parser = FileParser::FromData($data);
    $this->assertNotNull($parser->getFunction('select'));
  }

  public function specialTypeProvider(): array<array<string>> {
    return [
      [ 'dict' ], // HHVM >= 3.13
      [ 'vec' ], // HHVM >= 3.14
      [ 'keyset' ], // HHVM >= 3.15
    ];
  }

  /** @dataProvider specialTypeProvider */
  public function testSpecialReturnType(string $type): void {
    $data = '<?hh function foo(): '.$type.' {}';
    $parser = FileParser::FromData($data);
    $func = $parser->getFunction('foo');
    $this->assertSame(
      $type,
      $func->getReturnType()?->getTypeName(),
    );
  }

  /** @dataProvider specialTypeProvider */
  public function testSpecialTypeAsFuncName(string $type): void {
    $data = '<?hh function '.$type.'(): void {}';
    $parser = FileParser::FromData($data);
    $func = $parser->getFunction($type);
    $this->assertSame(
      'void',
      $func->getReturnType()?->getTypeName(),
    );
    $this->assertSame(
      $type,
      $func->getName(),
    );
  }

  /** @dataProvider specialTypeProvider */
  public function testSpecialTypeAsClassName(string $type): void {
    $data = '<?hh class '.$type.' { }';
    $parser = FileParser::FromData($data);
    $class = $parser->getClass($type);
    $this->assertNotNull($class);
  }

  public function testConstantCalledOn(): void {
    $data = '<?hh class Foo { const ON = 0; }';

    $this->assertEquals(
      Vector { 'ON' },
      FileParser::FromData($data)
      ->getClass('Foo')
      ->getConstants()
      ->map($x ==> $x->getName())
    );
  }

  public function testClassMagicConstant(): void {
    $data = "<?hh Foo::class;\nclass Foo{}";

    // This could throw because the ; comes after the keyword class
    $this->assertEquals(
      'Foo',
      FileParser::FromData($data)
      ->getClass('Foo')
      ->getName()
    );
  }

  public function testClassConstant(): void {
    $data = "<?hh Herp::DERP;\nclass Foo{}";

    $this->assertEquals(
      'Foo',
      FileParser::FromData($data)
      ->getClass('Foo')
      ->getName()
    );
  }

  /** The noramlization blacklist shouldn't apply to things we define */
  public function testNamespacedClassCalledCollection(): void {
    $data = '<?php namespace Foo\Bar; class Collection {}';

    $this->assertEquals(
      Vector { 'Foo\Bar\Collection' },
      FileParser::FromData($data)->getClassNames(),
    );
  }

  public function testNamespaceResolutionDependingOnSourceType(): void {
    $php = "<?php namespace Foo; class MyClass extends Collection {}";
    $hack = "<?hh namespace Foo; class MyClass extends Collection {}";

    $php_class = FileParser::FromData($php)->getClass("Foo\\MyClass");
    $hack_class = FileParser::FromData($hack)->getClass("Foo\\MyClass");

    $this->assertSame(
      "Foo\\Collection",
      $php_class->getParentClassName(),
    );
    $this->assertSame(
      'Collection',
      $hack_class->getParentClassName(),
    );
  }

  public function testScalarParameterInNamespace(): void {
    // This is correct for PHP7, not for PHP5 though. If you're using Hack,
    // you're more likely to be using scalar typehints than not.
    $php = '<?php namespace Foo; function myfunc(): string {}';
    $hack = '<?hh namespace Foo; function myfunc(): string {}';

    $php_func = FileParser::FromData($php)->getFunction("Foo\\myfunc");
    $hack_func = FileParser::FromData($hack)->getFunction("Foo\\myfunc");

    $this->assertEquals(
      'string',
      $php_func->getReturnType()?->getTypeName(),
    );
    $this->assertEquals(
      'string',
      $hack_func->getReturnType()?->getTypeName(),
    );
  }
}
