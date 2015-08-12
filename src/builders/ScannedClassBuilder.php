<?hh // strict

namespace FredEmmott\DefinitionFinder;

final class ScannedClassBuilder extends ScannedBaseBuilder {
  private ?ScannedScopeBuilder $scopeBuilder;

  public function __construct(
    private ClassDefinitionType $type,
    string $name,
  ) {
    parent::__construct($name);
  }

  public function setContents(ScannedScopeBuilder $scope): this {
    invariant($this->scopeBuilder === null, 'class already has a scope');
    $this->scopeBuilder = $scope;
    return $this;
  }

  // Can be safe in 3.9, assuming D2311514 is cherry-picked
  // public function build<T as ScannedClass>(classname<T> $what): T {
  public function build<T as ScannedClass>(string $what): T {
    {
      // UNSAFE
      ClassDefinitionType::assert($what::getType());
      invariant(
        $this->type === $what::getType(),
        "Can't build a %s for a %s",
        $what,
        token_name($this->type),
      );
    }

    $scope = nullthrows($this->scopeBuilder)
      ->setPosition(nullthrows($this->position))
      ->setNamespace('')
      ->build();

    $methods = $scope->getMethods();
    $properties = new Vector($scope->getProperties());

    foreach ($methods as $method) {
      if ($method->getName() === '__construct') {
        foreach ($method->getParameters() as $param) {
          if ($param->__isPromoted()) {
            // Not using the builder as we should have all the data up front,
            // and I want the typechecker to notice if we're missing something
            $properties[] = new ScannedProperty(
              nullthrows($this->position),
              $param->getName(),
              /* attributes = */ Map { },
              /* doc comment = */ null,
              $param->getTypehint(),
              $param->__getVisibility(),
              /* is static = */ false,
            );
          }
        }
        break;
      }
    }

    return /* UNSAFE_EXPR */ new $what(
      nullthrows($this->position),
      nullthrows($this->namespace).$this->name,
      nullthrows($this->attributes),
      $this->docblock,
      $methods,
      $properties,
    );
  }

  public function getType(): ClassDefinitionType {
    return $this->type;
  }
}
