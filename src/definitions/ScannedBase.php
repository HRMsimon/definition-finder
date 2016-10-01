<?hh // strict

namespace FredEmmott\DefinitionFinder;

abstract class ScannedBase {
  const type TContext = shape(
    'position' => SourcePosition,
    'sourceType' => SourceType,
  );
  // Namespace (e.g., of a class) if it exists
  private string $namespace;
  // Short name of the name without the namespace.
  // $shortname === $name if there is no namespace
  private string $shortName;

  public function __construct(
    private string $name,
    private self::TContext $context,
    private Map<string, Vector<mixed>> $attributes,
    private ?string $docComment,
  ) {
    list($this->namespace, $this->shortName) = $this->breakName($name);
  }

  abstract public static function getType(): ?DefinitionType;

  public function getPosition(): SourcePosition {
    return $this->context['position'];
  }

  public function getDocComment(): ?string {
    return $this->docComment;
  }

  public function getContext(): self::TContext {
    return $this->context;
  }

  public function getFileName(): string {
    return $this->context['position']['filename'];
  }

  public function getSourceType(): SourceType {
    return $this->context['sourceType'];
  }

  public function getName(): string {
    return $this->name;
  }

  public function getAttributes(): Map<string, Vector<mixed>> {
    return $this->attributes;
  }

  public function getNamespaceName(): string {
    return $this->namespace;
  }

  public function getShortName(): string {
    return $this->shortName;
  }

  // Break a name into its namespace (if exists) and short name.
  // Short name === name if no namespace
  private function breakName(string $name): (string, string) {
    $pos = strrpos($name, '\\');
    $ns = $pos !== false ? substr($name, 0, $pos) : '';
    $shortName = $ns !== '' ? substr($name, $pos + 1) : $name;
    return tuple($ns, $shortName);
  }
}
