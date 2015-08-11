<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;
use FredEmmott\DefinitionFinder\ScannedClass;

class ClassContentsTest extends \PHPUnit_Framework_TestCase {
  private ?ScannedClass $class;

  protected function setUp(): void {
    $parser = FileParser::FromFile(
      __DIR__.'/data/class_contents.php'
    );
    $this->class = $parser->getClasses()[0];
    $this->assertSame(
      'FredEmmott\\DefinitionFinder\\Test\\ClassWithContents',
      $this->class->getName(),
    );
  }

  public function testMethodNames(): void {
    $this->assertEquals(
      Vector {
        'publicMethod',
        'protectedMethod',
        'privateMethod',
        'PublicStaticMethod',
      },
      $this->class?->getMethods()?->map($x ==> $x->getName()),
    );
  }

  public function testMethodVisibility(): void {
    $this->assertEquals(
      Vector {true, false, false, true},
      $this->class?->getMethods()?->map($x ==> $x->isPublic()),
      'isPublic',
    );
    $this->assertEquals(
      Vector {false, true, false, false},
      $this->class?->getMethods()?->map($x ==> $x->isProtected()),
      'isProtected',
    );
    $this->assertEquals(
      Vector {false, false, true, false},
      $this->class?->getMethods()?->map($x ==> $x->isPrivate()),
      'isPrivate',
    );
  }

  /** Omitting public/protected/private is permitted in PHP */
  public function testDefaultMethodVisibility(): void {
    $parser = FileParser::FromFile(__DIR__.'/data/php_class_contents.php');
    $funcs = $parser->getClass('Foo')->getMethods();

    $this->assertEquals(
      Vector {
        'defaultVisibility',
        'privateVisibility',
        'alsoDefaultVisibility',
      },
      $funcs->map($x ==> $x->getName()),
    );
    $this->assertEquals(
      Vector { true, false, true },
      $funcs->map($x ==> $x->isPublic()),
    );
  }
  
  public function testMethodsAreStatic(): void {
    $this->assertEquals(
      Vector { false, false, false, true },
      $this->class?->getMethods()?->map($x ==> $x->isStatic()),
      'isPublic',
    );
  }

  public function testPropertyNames(): void {
    $this->assertEquals(
      Vector { 'foo', 'herp' },
      $this->class?->getProperties()?->map($x ==> $x->getName()),
    );
  }

  public function testPropertyVisibility(): void {
    $this->assertEquals(
      Vector { false, true },
      $this->class?->getProperties()?->map($x ==> $x->isPublic()),
    );
  }

  public function testPropertyTypes(): void {
    $this->assertEquals(
      Vector { 'bool', 'string' },
      $this->class?->getProperties()?->map(
        $x ==> $x->getTypehint()?->getTypeName()
      ),
    );
  }

  public function testTypelessProperty(): void {
    $parser = FileParser::FromFile(__DIR__.'/data/php_class_contents.php');
    $props = $parser->getClass('Foo')->getProperties();

    $this->assertEquals(
      Vector { 'untypedProperty' },
      $props->map($x ==> $x->getName()),
    );
    $this->assertEquals(
      Vector { null },
      $props->map($x ==> $x->getTypehint()),
    );
  }

  public function testStaticProperty(): void {
    $this->markTestIncomplete();
  }
}
