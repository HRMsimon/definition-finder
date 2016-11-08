<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class NamespaceStringExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    list($t, $ttype) = $tq->shift();
    if ($ttype === T_NS_SEPARATOR) {
      list($t, $ttype) = $tq->shift();
    }
    if ($ttype !== T_STRING) {
      return null;
    }
    $value = $t;

    while ($tq->haveTokens()) {
      list($t, $ttype) = $tq->peek();
      if ($ttype !== T_NS_SEPARATOR) {
        return new self($value);
      }
      $tq->shift();
      list($t, $ttype) = $tq->shift();
      if ($ttype !== T_STRING) {
        return null;
      }
      $value .= "\\".$t;
    }
    invariant_violation('Unexpected EOF');
  }
}