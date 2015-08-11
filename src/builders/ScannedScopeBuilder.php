<?hh // strict

namespace FredEmmott\DefinitionFinder;

class ScannedScopeBuilder extends ScannedSingleTypeBuilder<ScannedScope> {
  public function __construct() {
    parent::__construct('__SCOPE__');
  }

  private Vector<ScannedClassBuilder> $classBuilders = Vector { };
  private Vector<ScannedFunctionBuilder> $functionBuilders = Vector { };
  private Vector<ScannedMethodBuilder> $methodBuilders = Vector { };
  private Vector<ScannedConstantBuilder> $constantBuilders = Vector { };
  private Vector<ScannedEnumBuilder> $enumBuilders = Vector { };
  private Vector<ScannedTypeBuilder> $typeBuilders = Vector { };
  private Vector<ScannedNewtypeBuilder> $newtypeBuilders = Vector { };

  private Vector<ScannedNamespaceBuilder> $namespaceBuilders = Vector { };

  public function addClass(ScannedClassBuilder $b): void {
    $this->classBuilders[] = $b;
  }

  public function addFunction(ScannedFunctionBuilder $b): void {
    $this->functionBuilders[] = $b;
  }

  public function addMethod(ScannedMethodBuilder $b): void {
    $this->methodBuilders[] = $b;
  }

  public function addConstant(ScannedConstantBuilder $b): void {
    $this->constantBuilders[] = $b;
  }

  public function addEnum(ScannedEnumBuilder $b): void {
    $this->enumBuilders[] = $b;
  }

  public function addType(ScannedTypeBuilder $b): void {
    $this->typeBuilders[] = $b;
  }

  public function addNewtype(ScannedNewtypeBuilder $b): void {
    $this->newtypeBuilders[] = $b;
  }

  public function addNamespace(ScannedNamespaceBuilder $b): void {
    $this->namespaceBuilders[] = $b;
  }

  public function build(): ScannedScope {
    $ns = nullthrows($this->namespace);
    $pos = nullthrows($this->position);

    $classes = Vector { };
    $interfaces= Vector { };
    $traits = Vector { };
    foreach ($this->classBuilders as $b) {
      $b->setPosition($pos)->setNamespace($ns);
      switch ($b->getType()) {
        case ClassDefinitionType::CLASS_DEF:
          $classes[] = $b->build(ScannedBasicClass::class);
          break;
        case ClassDefinitionType::INTERFACE_DEF:
          $interfaces[] = $b->build(ScannedInterface::class);
          break;
        case ClassDefinitionType::TRAIT_DEF:
          $traits[] = $b->build(ScannedTrait::class);
          break;
      }
    }

    $functions = $this->buildAll($this->functionBuilders);
    $methods = $this->buildAll($this->methodBuilders);
    $constants = $this->buildAll($this->constantBuilders);
    $enums = $this->buildAll($this->enumBuilders);
    $types = $this->buildAll($this->typeBuilders);
    $newtypes = $this->buildAll($this->newtypeBuilders);

    $namespaces = $this->buildAll($this->namespaceBuilders);
    $scopes = $namespaces->map($ns ==> $ns->getContents());
    foreach ($scopes as $scope) {
      $classes->addAll($scope->getClasses());
      $interfaces->addAll($scope->getInterfaces());
      $traits->addAll($scope->getTraits());
      $functions->addAll($scope->getFunctions());
      $methods->addAll($scope->getMethods());
      $constants->addAll($scope->getConstants());
      $enums->addAll($scope->getEnums());
      $types->addAll($scope->getTypes());
      $newtypes->addAll($scope->getNewtypes());
    }

    return new ScannedScope(
      nullthrows($this->position),
      $classes,
      $interfaces,
      $traits,
      $functions,
      $methods,
      $constants,
      $enums,
      $types,
      $newtypes,
    );
  }

  private function buildAll<T>(
    \ConstVector<ScannedSingleTypeBuilder<T>> $v,
  ): Vector<T> {
    return $v->map($b ==> $b
      ->setPosition(nullthrows($this->position))
      ->setNamespace(nullthrows($this->namespace))
      ->build()
    )->toVector();
  }

  public function getNamespace(): string {
    return nullthrows($this->namespace);
  }
}
