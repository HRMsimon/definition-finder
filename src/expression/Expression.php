<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

abstract class Expression {
  final protected function __construct(private mixed $value) {}

  final public static function match(TokenQueue $tq): ?Expression {
    $state = $tq->getState();
    $ret = static::matchImpl($tq);
    if ($ret) {
      return $ret;
    }
    $tq->restoreState($state);
    return null;
  }

  abstract protected static function matchImpl(TokenQueue $tq): ?Expression;

  protected static function consumeWhitespace(TokenQueue $tq): void {
    while ($tq->haveTokens()) {
      list($_, $ttype) = $tq->peek();
      if ($ttype !== T_WHITESPACE) {
        return;
      }
      $tq->shift();
    }
  }

  final public function getValue(): mixed {
    return $this->value;
  }
}