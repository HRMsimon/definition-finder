<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticClassClassConstantExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    $class = NamespaceStringExpression::match($tq);
    if ($class === null) {
      return null;
    }
    list ($t, $ttype) = $tq->shift();
    if ($ttype !== T_DOUBLE_COLON) {
      return null;
    }
    list ($t, $ttype) = $tq->shift();
    if ($ttype !== T_CLASS) {
      return null;
    }
    return new self($class->getValue());
  }
}