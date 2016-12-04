<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;
use FredEmmott\DefinitionFinder\ScannedTypehint;

class StyleIssuesTest extends \PHPUnit_Framework_TestCase {
  public function testFunctionWithWhitespaceBeforeParamsList(): void {
    $data = '<?hh function foo ($bar) {};';
    $parser = FileParser::FromData($data);
    $fun = $parser->getFunction('foo');
    $this->assertEquals(
      Vector { 'bar' },
      $fun->getParameters()->map($x ==> $x->getName()),
    );
  }

  public function testFunctionWithWhitespaceBeforeReturnType(): void {
    $data = '<?hh function foo() : void {}';
    $parser = FileParser::FromData($data);
    $fun = $parser->getFunction('foo');
    $this->assertEquals(
      new ScannedTypehint('void', Vector { }, false),
      $fun->getReturnType(),
    );
  }

  public function testWhitespaceBetweenAttributes(): void {
    $data = '<?hh <<Herp, Derp>> function foo() {}';
    $parser = FileParser::FromData($data);
    $fun = $parser->getFunction('foo');
    $this->assertEquals(
      Vector {'Herp', 'Derp'},
      $fun->getAttributes()->keys(),
    );
  }

  public function testWhitespaceBetweenAttributesWithValue(): void {
    $data = '<?hh <<Herp("herpderp"), Derp>> function foo() {}';
    $parser = FileParser::FromData($data);
    $fun = $parser->getFunction('foo');
    $this->assertEquals(
      Map { 'Herp' => Vector { 'herpderp' }, 'Derp' => Vector {} },
      $fun->getAttributes(),
    );
  }

  public function testWhitespaceBetweenAttributeValues(): void {
    $data = '<?hh <<Foo("herp", "derp")>> function herp() {}';
    $parser = FileParser::FromData($data);
    $fun = $parser->getFunction('herp');
    $this->assertEquals(
      Map { 'Foo' => Vector { 'herp', 'derp' } },
      $fun->getAttributes(),
    );
  }

  public function testWhitespaceBetweenConcatenatedAttributeParts(): void {
    $data = '<?hh <<Foo("herp". "derp")>> function herp() {}';
    $parser = FileParser::FromData($data);
    $fun = $parser->getFunction('herp');
    $this->assertEquals(
      Map { 'Foo' => Vector { 'herpderp' } },
      $fun->getAttributes(),
    );
  }

  public function testTrailingCommaInAsyncReturnTuple(): void {
    $data = '<?hh async function herp(): Awaitable<(string, string, )> {}';
    $parser = FileParser::FromData($data);
    $this->assertContains('herp', $parser->getFunctionNames());
  }
}
