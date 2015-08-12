<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

use FredEmmott\DefinitionFinder\FileParser;

class SelfTest extends \PHPUnit_Framework_TestCase {

  public function filenameProvider(): array<array<string>> {
    return array_map(
      $filename ==> [basename($filename), $filename],
      glob(dirname(__DIR__).'/src/**/*.php'),
    );
  }

  /**
   * @dataProvider filenameProvider
   *
   * Bogus first argument to make test failure messages more useful
   */
  public function testSelf(string $_, string $filename): void {
    $parser = FileParser::FromFile($filename);
    $this->assertNotNull($parser);
  }

  public function elfSectionsProvider(): array<array<string>> {
    return $this->getSectionList()
      ->toVector()
      ->map($x ==> [$x])
      ->toArray();
  }

  /**
   * @dataProvider elfSectionsProvider
   */
  public function testELFSection(string $elf_section): void {
    try {
      $parser = FileParser::FromData($this->getSection($elf_section));
    } catch (\Exception $e) {
      file_put_contents(
        '/tmp/'.$elf_section.'.php',
        $this->getSection($elf_section),
      );
      throw $e;
    }
    $this->assertNotNull($parser);
  }

  <<__Memoize>>
  private function getReadelf(): string {
    $readelf = trim(shell_exec('which readelf'));
    if (!is_executable($readelf)) {
      $this->markTestSkipped('could not find readelf');
    }
    return $readelf;
  }

  private function getSection(
    string $section_name,
  ): string {
    invariant(
      $this->getSectionList()->contains($section_name),
      '%s is not a section name',
      $section_name,
    );

    $cmd = Vector {
      $this->getReadelf(),
      '--hex-dump', // --string-dump does some escaping :(
      $section_name,
      '--wide',
      PHP_BINARY,
    };
    $raw = shell_exec(implode(' ', $cmd->map($x ==>escapeshellarg($x))));
    $bytes = (new Vector(explode("\n", $raw)))
      // 0xADDR deadbeef deadbeef deadbeef deadbeef <?hh foo ba
      ->filter($line ==> strpos($line, '  0x') === 0)
      // deadbeefdeadbeefdeadbeefdeadbeef
      ->map($line ==> implode('', array_slice(explode(' ', trim($line)), 1, 4)))
      ->map($hex ==> hex2bin($hex));
    $bytes = implode('', $bytes);

    // array_map() and a few other functions in SystemLib are implemented in
    // HH ASsembly instead of Hack/PHP. These need to be ignored, and are always
    // at the end, after this marker.
    //
    // This approach is used in HPHP::systemlib_split() when HHVM laods
    // systemlib.
    $bytes = explode("\n<?hhas\n", $bytes)[0];

    return $bytes;
  }

  <<__Memoize>>
  private function getSectionList(): Set<string> {
    $parts = Vector {
      $this->getReadelf(),
      '--section-headers',
      PHP_BINARY
    };
    $sections = shell_exec(implode(' ', $parts->map($x ==> escapeshellarg($x))));
    $sections = (new Set(explode("\n", $sections)))
      ->filter($line ==> strpos($line, 'PROGBITS') !== false)
      ->map($line ==> preg_split('/\s+/', $line)[2])
      ->filter($name ==> preg_match('/^(ext\.|systemlib$)/', $name) !== 0);
    return $sections;
  }
}
