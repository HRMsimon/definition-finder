<?hh // strict

use \FredEmmott\DefinitionFinder\FileParser;

/**
 * 'function' is a valid keyword in several contexts other than when definining
 * a function; make sure they're not considered a function.
 */
final class FunctionNotDefinitionTest extends PHPUnit_Framework_TestCase {
  public function testActuallyAFunction(): void {
    $p = FileParser::FromData('<?hh function foo();');
    $this->assertEquals(Vector { 'foo' }, $p->getFunctions());
  }

  public function testFunctionTypeAlias(): void {
    $p = FileParser::FromData('<?hh newtype Foo = function(int): void;');
    $this->assertEquals(Vector { }, $p->getFunctions());
    $this->assertEquals(Vector { 'Foo' }, $p->getNewtypes());

    // Add extra whitespace
    $p = FileParser::FromData('<?hh newtype Foo = function (int): void;');
    $this->assertEquals(Vector { }, $p->getFunctions());
    $this->assertEquals(Vector { 'Foo' }, $p->getNewtypes());
  }

  public function testFunctionReturnType(): void {
    $p = FileParser::FromData(<<<EOF
<?hh
function foo(\$bar): (function():void) { return \$bar; }
EOF
    );
    $this->assertEquals(Vector { 'foo' }, $p->getFunctions());
  }

  public function testAsParameterType(): void {
    $p = FileParser::FromData('<?hh function foo((function():void) $callback) { }');
    $this->assertEquals(Vector { 'foo' }, $p->getFunctions());
  }

  public function testUsingAnonymousFunctions(): void {
    $p = FileParser::FromData(<<<EOF
<?hh
function foo() {
  \$x = function() { return 'bar'; };
  return \$x();
}
EOF
    );
    $this->assertEquals(Vector { 'foo' }, $p->getFunctions());
  }
}
