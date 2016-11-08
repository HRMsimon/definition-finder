<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class CommonScalarExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    // TODO: heredoc support (from common_scalar_ae)
    return StaticNumericScalarExpression::matchImpl($tq);
  }
}