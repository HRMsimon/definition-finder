<?hh // strict

use \FredEmmott\DefinitionFinder\FileParser;

// Usually, '{' becomes '{' - however, when used for
// string interpolation, you get a T_CURLY_OPEN.
//
// Interestingly enough, the matching '}' is still just '}' -
// there is no such thing as T_CURLY_CLOSE.
//
// This test makes sure that this doesn't get confused.
final class CurlyTest extends PHPUnit_Framework_TestCase {
  const string DATA_FILE = __DIR__.'/data/curly_then_function.php';

  public function testDefinitions(): void {
    $p = FileParser::FromFile(self::DATA_FILE);
    $this->assertEquals(Vector { 'Foo' }, $p->getClasses());
    $this->assertEquals(Vector { 'my_func' }, $p->getFunctions());
  }

  // Actually testing the tokenizer hasn't changed
  public function testContainsTCurlyOpen(): void {
    $matched = false;
    $tokens = token_get_all(file_get_contents(self::DATA_FILE));
    foreach ($tokens as $token) {
      if (is_array($token) && $token[0] === T_CURLY_OPEN) {
        $matched = true;
        break;
      }
    }
    $this->assertTrue($matched, 'no T_CURLY_OPEN in data file');
  }

  // Actually testing the tokenizer hasn't changed
  public function testDoesNotContainTCurlyClose(): void {
    $tokens = token_get_all(file_get_contents(self::DATA_FILE));
    foreach ($tokens as $token) {
      if (!is_array($token)) {
        continue;
      }
      $this->assertTrue(
        $token[1] !== '}',
        sprintf(
          'Got a token of type %d (%s) containing "}"',
          $token[0],
          token_name($token[0]),
        ),
      );
    }
  }
}
