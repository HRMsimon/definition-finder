<?hh // strict

namespace FredEmmott\DefinitionFinder;

final class TypehintConsumer extends Consumer {
  public function getTypehint(): ScannedTypehint {
    return $this->consumeType();
  }

  private function consumeType(): ScannedTypehint {
    $type = null;
    $generics = Vector { };

    $nesting = 0;
    while ($this->tq->haveTokens()) {
      list($t, $ttype) = $this->tq->shift();

      if ($ttype === T_WHITESPACE) {
        if ($nesting === 0) {
          break;
        }
        continue;
      }

      if ($nesting !== 0) {
        $type .= $t;
        if ($t === '(') {
          ++$nesting;
        }
      }

      // Handle functions
      if ($t === '(' && $nesting === 0) {
        $this->consumeWhitespace();
        list($t, $ttype) = $this->tq->peek();
        if ($ttype === T_FUNCTION) {
          $type = '(';
          ++$nesting;
          continue;
        }

        $type = 'tuple';
        while ($this->tq->haveTokens()) {
          $this->consumeWhitespace();
          $generics[] = $this->consumeType();
          $this->consumeWhitespace();

          list($t, $_) = $this->tq->shift();
          if ($t === ')') {
            break;
          }
          invariant(
            $t === ',',
            'expected ) or , after tuple member at line %d',
            $this->tq->getLine(),
          );
        }
        break;
      }

      if ($t === ')') {
        --$nesting;
        if ($nesting === 0) {
          break;
        }
        continue;
      }

      if (
        $ttype !== T_STRING
        && $ttype !== T_NS_SEPARATOR
        && $ttype !== T_CALLABLE
        && $ttype !== T_ARRAY
      ) {
        continue;
      }

      if ($nesting !== 0) {
        continue;
      }

      $type = $t;
      // Handle \foo
      if ($ttype === T_NS_SEPARATOR) {
        list($t, $_) = $this->tq->shift();
        $type .= $t;
      }

      // Handle \foo\bar and foo\bar
      while ($this->tq->haveTokens()) {
        list($_, $ttype) = $this->tq->peek();
        if ($ttype !== T_NS_SEPARATOR) {
          break;
        }
        $this->tq->shift();
        $type .= "\\";
        list($t, $_) = $this->tq->shift();
        $type .= $t;
      }

      // consume generics and recurse
      $this->consumeWhitespace();
      list($t, $ttype) = $this->tq->peek();
      if ($ttype === T_TYPELIST_LT) {
        $this->tq->shift();
        while ($this->tq->haveTokens()) {
          $this->consumeWhitespace();
          $generics[] = $this->consumeType();
          $this->consumeWhitespace();

          list($t, $ttype) = $this->tq->shift();
          if ($ttype === T_TYPELIST_GT) {
            break;
          }
          invariant(
            $t === ',',
            'expected > or , after generic type at line %d',
            $this->tq->getLine(),
          );
        }
        break;
      }
      break;
    }
    invariant($type !== null, 'expected a type at line %d', $this->tq->getLine());
    return new ScannedTypehint($type, $generics);
  }
}
