<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;
use FredEmmott\DefinitionFinder\ScannedClass;
use FredEmmott\DefinitionFinder\ScannedMethod;
use FredEmmott\DefinitionFinder\ScannedTypehint;

class TuplesTest extends \PHPUnit_Framework_TestCase {
  public function testTupleReturnType(): void {
    $data = '<?hh

<<__Native>>
function foo(): (string, string);
';

    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');

    $type = $function->getReturnType();
    $this->assertNotNull($type);
    assert($type !== null);

    $this->assertSame('tuple', $type->getTypeName());
    $this->assertSame('(string,string)', $type->getTypeText());
  }

  public function testContainerOfTuples(): void {
    $data = '<?hh

<<__Native>>
function foo(): Vector<(string, string)>;
';

    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');

    $return_type = $function->getReturnType();
    assert($return_type !== null);

    $this->assertSame('Vector', $return_type->getTypeName());
    $this->assertSame('Vector<(string,string)>', $return_type->getTypeText());
  }

  public function testTupleParameterType(): void {
    $data = '<?hh

function foo((string, string) $bar) {};
';

    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');

    $params = $function->getParameters();
    $this->assertEquals(
      Vector { '$bar' },
      $params->map($x ==> $x->getName()),
    );
    $this->assertEquals(
      ['(string,string)'],
      $params->map($x ==> $x->getTypehint()?->getTypeText()),
    );
  }

  private function sthToArray(?ScannedTypehint $typehint): ?array<mixed> {
    if ($typehint === null) {
      return null;
    }

    $generics = $typehint->getGenericTypes()->map(
      $x ==> $this->sthToArray($x),
    )->toArray();

    return [$typehint->getTypeName(), $generics];
  }
}
