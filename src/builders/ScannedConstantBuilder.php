<?hh // strict

namespace FredEmmott\DefinitionFinder;

class ScannedConstantBuilder extends ScannedSingleTypeBuilder<ScannedConstant> {
  public function __construct(
    string $name,
    self::TContext $context,
    private mixed $value,
    private ?ScannedTypehint $typehint,
    private AbstractnessToken $abstractness,
  ) {
    parent::__construct($name, $context);
  }

  public function build(): ScannedConstant {
    return new ScannedConstant(
      $this->name,
      $this->getDefinitionContext(),
      $this->docblock,
      $this->value,
      $this->typehint,
      $this->abstractness,
    );
  }
}
