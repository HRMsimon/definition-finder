<?hh // strict

namespace FredEmmott\DefinitionFinder;

class FileParser {
  // Temporary state
  private string $namespace = '';
  private Map<string,Vector<mixed>> $attributes = Map { };

  // Results
  private Vector<ScannedClass> $classes = Vector { };
  private Vector<ScannedFunction> $functions = Vector { };

  private Vector<string> $interfaces = Vector { };
  private Vector<string> $traits = Vector { };
  private Vector<string> $enums = Vector { };
  private Vector<string> $types = Vector { };
  private Vector<string> $newtypes = Vector { };
  private Vector<string> $constants = Vector { };

  private function __construct(
    private string $file,
    private TokenQueue $tokenQueue,
  ) {
    $this->consumeFile();
  }

  ///// Constructors /////

  public static function FromFile(
    string $filename,
  ): FileParser {
    return self::FromData(file_get_contents($filename), $filename);
  }

  public static function FromData(
    string $data,
    ?string $filename = null,
  ): FileParser {
    return new FileParser(
      $filename === null ? '__DATA__' : $filename,
      new TokenQueue($data),
    );
  }

  ///// Accessors /////

  public function getFilename(): string { return $this->file; }
  public function getClasses(): \ConstVector<ScannedClass> {
    return $this->classes;
  }
  public function getFunctions(): \ConstVector<ScannedFunction> {
    return $this->functions;
  }
  public function getInterfaces(): \ConstVector<string> { return $this->interfaces; }
  public function getTraits(): \ConstVector<string> { return $this->traits; }
  public function getEnums(): \ConstVector<string> { return $this->enums; }
  public function getTypes(): \ConstVector<string> { return $this->types; }
  public function getNewtypes(): \ConstVector<string> { return $this->newtypes; }
  public function getConstants(): \ConstVector<string> { return $this->constants; }

  ///// Convenience /////

  public function getClassNames(): \ConstVector<string> {
    return $this->getClasses()->map($class ==> $class->getName());
  }

  public function getFunctionNames(): \ConstVector<string> {
    return $this->getFunctions()->map($class ==> $class->getName());
  }

  ///// Implementation /////

  private function consumeFile(): void {
    $tq = $this->tokenQueue;
    $parens_depth = 0;
    while ($tq->haveTokens()) {
      $this->skipToCode();
      while ($tq->haveTokens()) {
        list ($token, $ttype) = $tq->shift();
        if ($token === '(') {
          ++$parens_depth;
        }
        if ($token === ')') {
          --$parens_depth;
        }

        if ($parens_depth !== 0 || $ttype === null) {
          continue;
        }

        if ($ttype === T_CLOSE_TAG) {
          break;
        }

        if ($ttype === T_SL) {
          $this->consumeUserAttributes();
        }

        if (DefinitionType::isValid($ttype)) {
          $this->consumeDefinition(DefinitionType::assert($ttype));
          continue;
        }
        // I hate you, PHP.
        if ($ttype === T_STRING && strtolower($token) === 'define') {
          $this->consumeOldConstantDefinition();
          continue;
        }
      }
    }
  }

  private function skipToCode(): void {
    $token_type = null;
    do {
      list ($token, $token_type) = $this->tokenQueue->shift();
    } while ($this->tokenQueue->haveTokens() && $token_type !== T_OPEN_TAG);
  }

  private function consumeDefinition(DefinitionType $def_type): void {
    $tname = token_name($def_type);

    $this->consumeWhitespace();

    switch ($def_type) {
      case DefinitionType::NAMESPACE_DEF:
        $this->consumeNamespaceDefinition();
        return;
      case DefinitionType::CLASS_DEF:
      case DefinitionType::INTERFACE_DEF:
      case DefinitionType::TRAIT_DEF:
        $this->consumeClassDefinition($def_type);
        return;
      case DefinitionType::FUNCTION_DEF:
        $this->consumeFunctionDefinition();
        return;
      case DefinitionType::CONST_DEF:
        $this->consumeConstantDefinition();
        return;
      case DefinitionType::TYPE_DEF:
      case DefinitionType::NEWTYPE_DEF:
      case DefinitionType::ENUM_DEF:
        $this->consumeSimpleDefinition($def_type);
        return;
    }
  }

  /**
   * /const CONST_NAME =/
   * /const type_name CONST_NAME =/
   */
  private function consumeConstantDefinition(): void {
    $name = null;
    while ($this->tokenQueue->haveTokens()) {
      list ($next, $next_type) = $this->tokenQueue->shift();
      if ($next_type === T_WHITESPACE) {
        continue;
      }
      if ($next_type === T_STRING) {
        $name = $next;
        continue;
      }
      if ($next === '=') {
        $this->constants[] = $this->namespace.$name;
        return;
      }
    }
    $this->consumeStatement();
  }

  /**
   * define ('FOO', value);
   * define (FOO, value); // yep, this is different. I *REALLY* hate php.
   *
   * 'define' has been consumed, that's it
   */
  private function consumeOldConstantDefinition(): void {
    $tq = $this->tokenQueue;

    $this->consumeWhitespace();
    list($next, $_) = $tq->shift();
    invariant(
      $next === '(',
      'Expected define to be followed by a paren in %s',
      $this->file,
    );
    $this->consumeWhitespace();
    list ($next, $next_type) = $tq->shift();
    invariant(
      $next_type === T_CONSTANT_ENCAPSED_STRING || $next_type === T_STRING,
      'Expected arg to define() to be a T_CONSTANT_ENCAPSED_STRING or '.
      'T_STRING, got %s in %s',
      token_name($next_type),
      $this->file,
    );
    $name = $next;
    if ($next_type === T_STRING) {
      // CONST_NAME
      $this->constants[] = $this->namespace.$name;
    } else {
      // 'CONST_NAME' or "CONST_NAME"
      invariant(
        $name[0] == $name[strlen($name) - 1],
        'Mismatched quotes',
      );
      $this->constants[] = $this->namespace.
        substr($name, 1, strlen($name) - 2);
    }
    $this->consumeStatement();
  }

  private function consumeWhitespace(): void {
    list($t, $ttype) = $this->tokenQueue->shift();
    if ($ttype === T_WHITESPACE) {
      return;
    }
    $this->tokenQueue->unshift($t, $ttype);
  }

  private function consumeNamespaceDefinition(): void {
    $parts = [];
    do {
      $this->consumeWhitespace();
      list($next, $next_type) = $this->tokenQueue->shift();
      if ($next_type === T_STRING) {
        $parts[] = $next;
        continue;
      } else if ($next_type === T_NS_SEPARATOR) {
        continue;
      } else if ($next === '{' || $next === ';') {
        break;
      }
      invariant_violation(
        'Unexpected token %s in %s',
        var_export($next, true),
        $this->file,
      );
    } while ($this->tokenQueue->haveTokens());

    if ($parts) {
      $this->namespace = implode('\\', $parts).'\\';
    } else {
      $this->namespace = '';
    }
  }

  private function skipToAndConsumeBlock(): void {
    $nesting = 0;
    while ($this->tokenQueue->haveTokens()) {
      list($next, $next_type) = $this->tokenQueue->shift();
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

  private function consumeStatement(): void {
    while ($this->tokenQueue->haveTokens()) {
      list($tv, $ttype) = $this->tokenQueue->shift();
      if ($tv === ';') {
        return;
      }
      if ($tv === '{') {
        $this->tokenQueue->unshift($tv, $ttype);
        $this->skipToAndConsumeBlock();
        return;
      }
    }
  }

  private function consumeClassDefinition(DefinitionType $def_type): void {
    list($v, $t) = $this->tokenQueue->shift();
    if ($t === T_STRING) {
      $name = $v;
    } else {
      invariant(
        $t === T_XHP_LABEL,
        'Unknown class token %d in %s',
        token_name($t),
        $this->file,
      );
      invariant(
        $def_type === DefinitionType::CLASS_DEF,
        'Seeing an XHP class name for a %s in %s',
        token_name($def_type),
        $this->file,
      );
      // 'class :foo:bar' is really 'class xhp_foo__bar'
      $name = 'xhp_'.str_replace(':', '__', substr($v, 1));
    }
    $fqn = $this->namespace.$name;
    switch ($def_type) {
      case DefinitionType::CLASS_DEF:
        $this->classes[] = new ScannedClass(
          shape('filename' => $this->file),
          $fqn,
          $this->attributes,
        );
        break;
      case DefinitionType::INTERFACE_DEF:
        $this->interfaces[] = $fqn;
        break;
      case DefinitionType::TRAIT_DEF:
        $this->traits[] = $fqn;
        break;
      default:
        invariant_violation(
          'Trying to define %s as a class',
          token_name($def_type),
        );
    }
    $this->attributes = Map { };
    $this->skipToAndConsumeBlock();
  }

  private function consumeSimpleDefinition(DefinitionType $def_type): void {
    list($next, $next_type) = $this->tokenQueue->shift();
    invariant(
      $next_type === T_STRING,
      'Expected a string for %s, got %d - in %s',
      token_name($def_type),
      $next_type,
      $this->file,
    );
    $fqn = $this->namespace.$next;
    switch ($def_type) {
      case DefinitionType::TYPE_DEF:
        $this->types[] = $fqn;
        break;
      case DefinitionType::NEWTYPE_DEF:
        $this->newtypes[] = $fqn;
        break;
      case DefinitionType::ENUM_DEF:
        $this->enums[] = $fqn;
        $this->skipToAndConsumeBlock();
        return;
      default:
        invariant_violation(
          '%d is not a simple definition',
          $def_type,
        );
    }
    $this->consumeStatement();
  }

  private function consumeFunctionDefinition(): void {
    $builder = (new FunctionConsumer($this->tokenQueue))->getBuilder();
    if (!$builder) {
      return;
    }
    $this->functions[] = $builder
      ->setNamespace($this->namespace)
      ->setPosition(shape('filename' => $this->file))
      ->setAttributes($this->attributes)
      ->build();
    $this->attributes = Map { };
  }

  private function consumeUserAttributes(): void {
    while (true) {
      list($name, $_) = $this->tokenQueue->shift();
      if (!$this->attributes->containsKey($name)) {
        $this->attributes[$name] = Vector { };
      }

      list($t, $ttype) = $this->tokenQueue->shift();
      if ($ttype === T_SR) { // this was the last attribute
        return;
      }
      if ($t === ',') { // there's another
        continue;
      }

      // this attribute has values
      invariant(
        $t === '(',
        'Expected attribute name to be followed by >>, (, or ,',
      );

      while (true) {
        list($value, $ttype) = $this->tokenQueue->shift();
        switch ((int) $ttype) {
          case T_CONSTANT_ENCAPSED_STRING:
            $this->attributes[$name][] = substr($value, 1, -1);
            break;
          case T_LNUMBER:
            $this->attributes[$name][] = (int) $value;
            break;
          default:
            invariant_violation(
              "Invalid attribute value token type: %d",
              $ttype
            );
        }
        list($t, $_) = $this->tokenQueue->shift();
        if ($t === ')') {
          break;
        }
        invariant($t === ',', 'Expected attribute value to be followed by , or )');
      }
      list($t, $ttype) = $this->tokenQueue->shift();
      if ($ttype === T_SR) {
        return;
      }
      invariant(
        $t === ',',
        'Expected attribute value list to be followed by >> or ,',
      );
    }
  }
}
