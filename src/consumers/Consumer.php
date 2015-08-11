<?hh // strict

namespace FredEmmott\DefinitionFinder;

abstract class Consumer {
  public function __construct(
    protected TokenQueue $tq,
  ) {
  }

  protected function consumeWhitespace(): void {
    if ($this->tq->isEmpty()) {
      return;
    }
    list($t, $ttype) = $this->tq->shift();
    if ($ttype === T_WHITESPACE) {
      return;
    }
    $this->tq->unshift($t, $ttype);
  }

  protected function consumeStatement(): void {
    while ($this->tq->haveTokens()) {
      list($tv, $ttype) = $this->tq->shift();
      if ($tv === ';') {
        return;
      }
      if ($tv === '{') {
        $this->consumeBlock();
        return;
      }
    }
  }

  protected function skipToBlock(): void {
    while ($this->tq->haveTokens()) {
      list($next, $next_type) = $this->tq->shift();
      if ($next === '{' || $next_type === T_CURLY_OPEN) {
        return;
      }
    }
    invariant_violation('no block');
  }

  protected function consumeBlock(): void {
    $nesting = 1;
    while ($this->tq->haveTokens()) {
      list($next, $next_type) = $this->tq->shift();
      if ($next === '{' || $next_type === T_CURLY_OPEN) {
        ++$nesting;
      } else if ($next === '}') { // no such thing as T_CURLY_CLOSE
        --$nesting;
        if ($nesting === 0) {
          return;
        }
      }
    }
  }
}
