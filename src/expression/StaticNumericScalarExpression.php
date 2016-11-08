<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticNumericScalarExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    list($t, $ttype) = $tq->shift();
    if ($ttype === null) {
      return null;
    }

    switch ($ttype) {
      case T_LNUMBER:
      case T_DNUMBER:
      case T_ONUMBER:
        return new self((int) $t);
    }
    return null;
  }
}