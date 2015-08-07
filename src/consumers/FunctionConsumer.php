<?hh // strict

namespace FredEmmott\DefinitionFinder;

class FunctionConsumer extends Consumer {
  public function getBuilder(): ?ScannedFunctionBuilder {
    $by_ref_return = false;

    $tq = $this->tq;
    list($t, $ttype) = $tq->shift();

    if ($t === '&') {
      $by_ref_return = true;
      $this->consumeWhitespace();
      list($t, $ttype) = $tq->shift();
    }

    if ($t === '(') {
      // rvalue, eg '$x = function() { }'
      $this->consumeStatement();
      return null;
    }

    invariant($ttype === T_STRING, 'Expected function name');
    $name = $t;
 
    list($_, $ttype) = $tq->peek();
    $generics = Vector { };
    if ($ttype === T_TYPELIST_LT) {
      $generics = $this->consumeGenerics();
    }
    $params = $this->consumeParameterList();

    return (new ScannedFunctionBuilder($name))
      ->setByRefReturn($by_ref_return)
      ->setGenerics($generics);
  }

  private function consumeParameterList(): \ConstVector<(?string, string)> {
    $tq = $this->tq;
    list($t, $ttype) = $tq->shift();
    invariant($t === '(', 'expected parameter list, got %s', $t);

    $params = Vector { };
    $param_type = null;
    while ($tq->haveTokens()) {
      list($t, $ttype) = $tq->shift();

      if ($t === ')') {
        break;
      }

      if ($ttype === T_VARIABLE) {
        $params[] = tuple($param_type, $t);
        $param_type = null;
        continue;
      }

      if ($ttype === T_WHITESPACE || $t === ',') {
        continue;
      }
      
      invariant(
        $param_type === null,
        'found two things that look like typehints for the same parameter',
      );
      $tq->unshift($t, $ttype);
      $param_type = $this->consumeType();
    }
    return $params;
  }

  private function consumeType(): string {
    $type = '';
    $nesting = 0;
    while ($this->tq->haveTokens()) {
      list($t, $ttype) = $this->tq->shift();

      if ($ttype === T_WHITESPACE) {
        if ($nesting === 0) {
          break;
        }
        continue;
      }

      $type .= $t;
      if ($t === '{' || $ttype === T_TYPELIST_LT || $t === '(') {
        ++$nesting;
      }
      if ($t === '}' || $ttype === T_TYPELIST_GT || $t === ')') {
        --$nesting;
        if ($nesting === 0) {
          break;
        }
      }
    }
    return $type;
  }

  private function consumeGenerics(): \ConstVector<ScannedGeneric> {
    $tq = $this->tq;
    list($t, $ttype) = $tq->shift();
    invariant($ttype = T_TYPELIST_LT, 'Consuming generics, but not a typelist');

    $ret = Vector { };

    $name = null;
    $constraint = null;

    while ($tq->haveTokens()) {
      list($t, $ttype) = $tq->shift();

      invariant(
        $ttype !== T_TYPELIST_LT,
        "nested generic type",
      );

      if ($ttype === T_WHITESPACE) {
        continue;
      }

      if ($ttype === T_TYPELIST_GT) {
        if ($name !== null) {
          $ret[] = new ScannedGeneric($name, $constraint);
        }
        return $ret;
      }

      if ($t === ',') {
        $ret[] = new ScannedGeneric(nullthrows($name), $constraint);
        $name = null;
        $constraint = null;
        continue;
      }

      if ($name === null) {
        invariant($ttype === T_STRING, 'expected type variable name');
        $name = $t;
        continue;
      }

      if ($ttype === T_AS) {
        continue;
      }

      invariant($ttype === T_STRING, 'expected type constraint');
      $constraint = $t;
    }
    invariant_violation('never reached end of generics definition');
  }
}
