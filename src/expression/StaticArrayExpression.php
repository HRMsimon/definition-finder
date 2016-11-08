<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticArrayExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    list($t, $ttype) = $tq->shift();
    if ($t === '[') {
      $end = ']';
    } else if ($ttype === T_ARRAY) {
      list ($t, $ttype) = $tq->shift();
      if ($t !== '(') {
        return null;
      }
      $end = ')';
    } else {
      return null;
    }

    $values = StaticArrayPairListExpression::match($tq);
    if ($values === null) {
      $values = StaticArrayListExpression::match($tq);
    }
    $values = $values?->getValue() ?? [];

    list($t, $_) = $tq->shift();
    if ($t !== $end) {
      return null;
    }
    return new self($values);
  }
}