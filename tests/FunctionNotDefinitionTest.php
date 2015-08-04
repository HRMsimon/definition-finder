<?hh // strict

use \FredEmmott\DefinitionFinder\FileParser;

/**
 * 'function' is a valid keyword in several contexts other than when definining
 * a function; make sure they're not considered a function.
 */
final class FunctionNotDefinitionTest extends PHPUnit_Framework_TestCase {
  public function testActuallyAFunction(): void {
    $p = FileParser::FromData('<?hh function foo();');
    $this->assertEquals(Vector { 'foo' }, $p->getFunctionNames());
  }

  public function testFunctionTypeAlias(): void {
    $p = FileParser::FromData('<?hh newtype Foo = function(int): void;');
    $this->assertEquals(Vector { }, $p->getFunctionNames());
    $this->assertEquals(Vector { 'Foo' }, $p->getNewtypeNames());

    // Add extra whitespace
    $p = FileParser::FromData('<?hh newtype Foo = function (int): void;');
    $this->assertEquals(Vector { }, $p->getFunctionNames());
    $this->assertEquals(Vector { 'Foo' }, $p->getNewtypeNames());
  }

  public function testFunctionReturnType(): void {
    $p = FileParser::FromData(<<<EOF
<?hh
function foo(\$bar): (function():void) { return \$bar; }
EOF
    );
    $this->assertEquals(Vector { 'foo' }, $p->getFunctionNames());
  }

  public function testAsParameterType(): void {
    $p = FileParser::FromData('<?hh function foo((function():void) $callback) { }');
    $this->assertEquals(Vector { 'foo' }, $p->getFunctionNames());
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
    $this->assertEquals(Vector { 'foo' }, $p->getFunctionNames());
  }

  public function testAsParameter(): void {
    $p = FileParser::FromData(<<<EOF
<?php
spl_autoload_register(function(\$class) { });
function foo() { }
EOF
    );
    $this->assertEquals(Vector { 'foo' }, $p->getFunctionNames());
  }

  public function testAsRVal(): void {
    $p = FileParser::FromData('<?php $f = function(){};');
    $this->assertEmpty($p->getFunctionNames());
  }
}
