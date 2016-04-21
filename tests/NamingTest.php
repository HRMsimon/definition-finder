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

  public function testReturnTypeCalledDict(): void {
    // Separate token in HHVM > 3.13
    $data = '<?hh function foo(): dict {}';
    $parser = FileParser::FromData($data);
    $func = $parser->getFunction('foo');
    $this->assertSame(
      'dict',
      $func->getReturnType()?->getTypeName(),
    );
  }

  public function testClassCalledDict(): void {
    // Separate token in HHVM > 3.13
    $data = '<?hh class dict {}';
    $parser = FileParser::FromData($data);
    $this->assertNotNull($parser->getClass('dict'));
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
}
