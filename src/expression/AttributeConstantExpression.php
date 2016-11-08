<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class AttributeConstantExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {
    list($t, $_) = $tq->shift();
    switch(strtolower($t)) {
      case 'true':
        return new self(true);
      case 'false':
        return new self(false);
      case 'null':
        return new self(null);
      case 'inf':
        return new self(INF);
      case 'nan':
        return new self(NAN);
      default:
        return null;
    }
  }
}