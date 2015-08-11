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

    /* Regex taken from http://php.net/manual/en/functions.user-defined.php
     *
     * Some things other than T_STRING are valid, eg 'function select() {}' has
     * a T_SELECT
     */
    invariant(
      preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $t) === 1,
      'Expected function name at line %d',
      $tq->getLine(),
    );
    $name = $t;

    $builder = (new ScannedFunctionBuilder($name))
      ->setByRefReturn($by_ref_return);
 
    list($_, $ttype) = $tq->peek();
    $generics = Vector { };
    if ($ttype === T_TYPELIST_LT) {
      $generics = $this->consumeGenerics();
    }
    $builder
      ->setGenerics($generics)
      ->setParameters($this->consumeParameterList());

    $this->consumeWhitespace();
    list($t, $ttype) = $tq->peek();
    if ($t === ':') {
      $tq->shift();
      $this->consumeWhitespace();
      $builder->setReturnType($this->consumeType());
    }
    return $builder;
  }

  private function consumeParameterList(): \ConstVector<ScannedParameter> {
    $this->consumeWhitespace();
    $tq = $this->tq;
    list($t, $ttype) = $tq->shift();
    invariant(
      $t === '(',
      'expected parameter list, got "%s" (%d) at line %d',
      $t,
      $ttype,
      $this->tq->getLine(),
    );

    $params = Vector { };

    $visibility = null;
    $param_type = null;
    $byref = false;
    $variadic = false;
    while ($tq->haveTokens()) {
      list($t, $ttype) = $tq->shift();

      if ($t === ')') {
        break;
      }

      if ($t === '&') {
        $byref = true;
        continue;
      }
      if ($ttype === T_ELLIPSIS) {
        $variadic = true;
        continue;
      }

      if ($ttype === T_VARIABLE) {
        $default = $this->consumeDefaultValue();
        $name = substr($t, 1); // remove '$'
        if (!$variadic) {
          invariant(
            count($params->filter($param ==> $param->isVariadic())) === 0,
            'non-variadic parameter after variadic at line %d',
            $tq->getLine(),
          );
        }
        $params[] = new ScannedParameter(
          $name,
          $param_type,
          $byref,
          $variadic,
          $default,
        );
        $param_type = null;
        $visibility = null;
        $byref = false;
        $variadic = false;
        continue;
      }

      if ($ttype === T_WHITESPACE || $t === ',' || $ttype === T_COMMENT) {
        continue;
      }

      if (
        $ttype === T_PRIVATE
        || $ttype === T_PUBLIC
        || $ttype === T_PROTECTED
      ) {
        $visibility = $ttype;
        continue;
      }
      
      invariant(
        $param_type === null,
        'found two things that look like typehints for the same parameter '.
        'at line %d',
        $tq->getLine(),
      );
      $tq->unshift($t, $ttype);
      $param_type = $this->consumeType();
    }
    return $params;
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

      // Handle functions
      if ($t === '(') {
        $this->consumeWhitespace();
        list($t, $ttype) = $this->tq->peek();
        if ($ttype === T_FUNCTION) {
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

  private function consumeDefaultValue(): ?string {
    $this->consumeWhitespace();
    list($t, $_) = $this->tq->peek();
    if ($t !== '=') {
      return null;
    }

    $this->tq->shift();
    $nesting = 0;
    $default = '';
    while($this->tq->haveTokens()) {
      $this->consumeWhitespace();
      list($t, $_) = $this->tq->peek();

      if ($nesting === 0) {
        if ($t === ',' || $t === ')') {
          break;
        }
      }
      $this->tq->shift();

      $default .= $t;
      if ($t === '(') {
        $nesting++;
        continue;
      }

      if ($t === ')') {
        $nesting--;
        if ($nesting === 0) {
          break;
        }
        continue;
      }
    }

    return $default;
  }
}
