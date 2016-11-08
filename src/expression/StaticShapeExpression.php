<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticShapeExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    list($t, $ttype) = $tq->shift();
    if ($ttype !== T_SHAPE) {
      return null;
    }
    list ($t, $ttype) = $tq->shift();
    if ($t !== '(') {
      return null;
    }

    $values = StaticArrayPairListExpression::match($tq);
    $values = $values?->getValue() ?? []; // empty shape is fine

    list($t, $_) = $tq->shift();
    if ($t !== ')') {
      return null;
    }
    return new self($values);
  }
}