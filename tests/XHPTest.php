<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;
use FredEmmott\DefinitionFinder\ScannedClass;

class XHPTest extends \PHPUnit_Framework_TestCase {
  public function testXHPRootClass(): void {
    $data = '<?hh class :foo:bar {}';

    $parser = FileParser::FromData($data);
    $this->assertContains(
      'xhp_foo__bar',
      $parser->getClassNames(),
    );
  }

  public function testXHPClassWithParent(): void {
    $data = '<?hh class :foo:bar extends :herp:derp {}';

    $parser = FileParser::FromData($data);
    $this->assertContains(
      'xhp_foo__bar',
      $parser->getClassNames(),
    );

    $this->assertSame(
      'xhp_herp__derp',
      $parser->getClass('xhp_foo__bar')->getParentClassName(),
    );
  }

  public function testXHPEnumAttributeParses(): void {
    // XHP Attributes are not reported, but shouldn't cause parse errors
    $data = '<?hh class :foo:bar { attribute enum { "herp", "derp" } myattr @required; }';

    $parser = FileParser::FromData($data);
    $this->assertContains(
      'xhp_foo__bar',
      $parser->getClassNames(),
    );
  }

  public function testXHPEnumAttributesParse(): void {
    // StatementConsumer was getting confused by the brace
    $data = <<<EOF
<?hh class :example {
  attribute
    enum { "foo", "bar" } myattr @required,
    enum { "herp", "derp" } myattr2 @required;
}
EOF;

    $parser = FileParser::FromData($data);
    $this->assertContains(
      'xhp_example',
      $parser->getClassNames(),
    );
  }

  public function testXHPClassNamesAreCorrect(): void {
    $parser = FileParser::FromData('<?hh class :foo:bar:baz:herp-derp {}');

    $this->assertContains(
      /* UNSAFE_EXPR */ :foo:bar:baz:herp-derp::class,
      $parser->getClassNames()->get(0)
    );
  }
}
