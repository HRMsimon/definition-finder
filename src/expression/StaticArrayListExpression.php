<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticArrayListExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    $values = [];
    while ($tq->haveTokens()) {
      self::consumeWhitespace($tq);
      $expr = StaticScalarExpression::match($tq);
      if (!$expr) {
        if ($values) {
          // Trailing comma
          return new self($values);
        }
        return null;
      }
      $values[] = $expr->getValue();
      self::consumeWhitespace($tq);
      list ($t, $_) = $tq->peek();
      if ($t !== ',') {
        return new self($values);
      }
      $tq->shift();
      self::consumeWhitespace($tq);
    }
    return null;
  }
}