<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class PlusMinusStaticNumericScalarExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    list($t, $_) = $tq->shift();
    if ($t !== '+' && $t !== '-') {
      return null;
    }
    $match = StaticNumericScalarExpression::match($tq);
    if (!$match) {
      return null;
    }
    return new self((int) ($t.(string)$match->getValue()));
  }
}