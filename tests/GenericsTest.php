<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;
use FredEmmott\DefinitionFinder\RelationshipToken;

class GenericsTest extends \PHPUnit_Framework_TestCase {
  public function testClassHasGenerics(): void {
    $data = '<?hh class Foo<Tk, Tv> {}';
    $parser = FileParser::FromData($data);
    $class = $parser->getClass('Foo');

    $this->assertEquals(
      Vector { 'Tk', 'Tv' },
      $class->getGenericTypes()->map($x ==> $x->getName()),
    );

    $this->assertEquals(
      Vector { null, null },
      $class->getGenericTypes()->map($x ==> $x->getConstraintTypeName()),
    );
  }

  public function testFunctionHasGenerics(): void {
    $data = '<?hh function foo<Tk, Tv>(){}';
    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');

    $this->assertEquals(
      Vector { 'Tk', 'Tv' },
      $function->getGenericTypes()->map($x ==> $x->getName()),
    );

    $this->assertEquals(
      Vector { null, null },
      $function->getGenericTypes()->map($x ==> $x->getConstraintTypeName()),
    );
  }

  public function testConstrainedGenerics(): void {
    $data = '<?hh class Foo<T1 as Bar, T2 super Baz> {}';
    $parser = FileParser::FromData($data);
    $class = $parser->getClass('Foo');

    $this->assertEquals(
      Vector { 'Bar', 'Baz' },
      $class->getGenericTypes()->map($x ==> $x->getConstraintTypeName()),
    );
    $this->assertEquals(
      Vector { RelationshipToken::SUBTYPE, RelationshipToken::SUPERTYPE },
      $class->getGenericTypes()->map($x ==> $x->getConstraintRelationship()),
    );
  }
  public function testNamespacedConstrainedGenerics(): void {
    $data = '<?hh class Foo<T as \Bar\Baz> {}';
    $parser = FileParser::FromData($data);
    $class = $parser->getClass('Foo');

    $this->assertEquals(
      Vector { 'Bar\Baz' },
      $class->getGenericTypes()->map($x ==> $x->getConstraintTypeName()),
    );
  }

  public function testVariance(): void {
    $data = '<?hh class Foo<-Ta, Tb, +Tc> {}';
    $parser = FileParser::FromData($data);
    $class = $parser->getClass('Foo');
    $generics = $class->getGenericTypes();

    $this->assertEquals(
      Vector { 'Ta', 'Tb', 'Tc' },
      $generics->map($x ==> $x->getName()),
    );
    $this->assertEquals(
      Vector { true, false, false },
      $generics->map($x ==> $x->isContravariant()),
    );
    $this->assertEquals(
      Vector { false, true, false },
      $generics->map($x ==> $x->isInvariant()),
    );
    $this->assertEquals(
      Vector { false, false, true},
      $generics->map($x ==> $x->isCovariant()),
    );
  }

  public function testVectorLikeArrayParam(): void {
    $data = '<?hh function foo(array<SomeClass> $param): void {}';
    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');
  }

  public function testVectorLikeArrayOfPrimitivesParam(): void {
    $data = '<?hh function foo(array<string> $param): void {}';
    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');
  }

  public function testMapLikeArrayParam(): void {
    $data = '<?hh function foo(array<string, PharFileInfo> $list): void {}';
    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');
  }

  public function testInlineShapeConstraint(): void {
    $data = '<?hh function foo<T as shape()>(): void {}';
    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');
    $generics = $function->getGenericTypes();
    $this->assertSame(
      'shape()',
      $generics[0]->getConstraintTypeName(),
    );
  }

  public function testGenericWithTrailingComma(): void {
    /* HH_FIXME[4106] HHVM_VERSION not defined */
    /* HH_FIXME[2049] HHVM_VERSION not defined */
    if (!version_compare(HHVM_VERSION, '3.12.0', '>=')) {
      $this->markTestSkipped('only supported on 3.12+');
    }
    $data = '<?hh function foo(ImmMap<string,string,> $bar): void {}';
    $parser = FileParser::FromData($data);
    $function = $parser->getFunction('foo');
    $param_types = $function->getParameters()->map(
      $param ==> $param->getTypehint()?->getTypeText(),
    );
    $this->assertEquals(
      Vector { 'ImmMap<string,string>' },
      $param_types,
    );
  }
}
