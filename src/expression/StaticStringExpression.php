<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticStringExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    $value = '';
    do {
      list($t, $ttype) = $tq->shift();
      if ($ttype !== T_CONSTANT_ENCAPSED_STRING) {
        return null;
      }
      $value .= substr($t, 1, -1); // remove wrapping quotes

      self::consumeWhitespace($tq);
      list($t, $_) = $tq->peek();
      if ($t !== '.') {
        return new self($value);
      }
      $tq->shift();
      self::consumeWhitespace($tq);
    } while ($tq->haveTokens());
    invariant_violation('Unexpected EOF');
  }
}