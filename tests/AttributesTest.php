<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;
use FredEmmott\DefinitionFinder\ScannedBase;
use FredEmmott\DefinitionFinder\ScannedClass;
use FredEmmott\DefinitionFinder\ScannedFunction;

class ClassAttributesTest extends \PHPUnit_Framework_TestCase {
  private \ConstVector<ScannedClass> $classes = Vector {};
  private \ConstVector<ScannedFunction> $functions = Vector {};

  protected function setUp(): void {
    $parser = FileParser::FromFile(
      __DIR__.'/data/attributes.php'
    );
    $this->classes = $parser->getClasses();
    $this->functions = $parser->getFunctions();
  }

  public function testSingleSimpleAttribute(): void {
    $class = $this->findClass('ClassWithSimpleAttribute');
    $this->assertEquals(
      Map { "Foo" => Vector { } },
      $class->getAttributes(),
    );
  }

  public function testMultipleSimpleAttributes(): void {
    $class = $this->findClass('ClassWithSimpleAttributes');
    $this->assertEquals(
      Map { "Foo" => Vector { }, "Bar" => Vector { } },
      $class->getAttributes(),
    );
  }

  public function testWithSingleStringAttribute(): void {
    $class = $this->findClass('ClassWithStringAttribute');
    $this->assertEquals(
      Map { 'Herp' => Vector {'derp'} },
      $class->getAttributes(),
    );
  }

  public function testWithSingleIntAttribute(): void {
    $class = $this->findClass('ClassWithIntAttribute');
    $this->assertEquals(
      Map { 'Herp' => Vector {123} },
      $class->getAttributes(),
    );
    // Check it's an int, not a string
    $this->assertSame(
      123,
      $class->getAttributes()['Herp'][0],
    );
  }

  public function testFunctionHasAttributes(): void {
    $func = $this->findScanned($this->functions, 'function_after_classes');
    $this->assertEquals(
      Map { 'FunctionFoo' => Vector { } },
      $func->getAttributes(),
    );
  }

  public function testFunctionAttrsDontPolluteClass(): void {
    $class = $this->findClass('ClassAfterFunction');
    $this->assertEquals(
      Map { 'ClassFoo' => Vector {} },
      $class->getAttributes(),
    );
  }

  private function findScanned<T as ScannedBase>(
    \ConstVector<T> $container,
    string $name, 
  ): T {
    foreach ($container as $scanned) {
      if ($scanned->getName() === "FredEmmott\\DefinitionFinder\\Test\\".$name) {
        return $scanned;
      }
    }
    invariant_violation('Could not find scannned%s', $name);
  }

  private function findClass(string $name): ScannedClass {
    return $this->findScanned($this->classes, $name);
  }
}
