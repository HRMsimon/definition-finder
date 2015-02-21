<?hh

namespace FredEmmott\DefinitionFinder;

class FileParser {
  private ?string $data;
  private array $tokens = [];
  private string $namespace = '';
  private Vector<string> $classes = Vector { };
  private Vector<string> $interfaces = Vector { };
  private Vector<string> $traits = Vector { };
  private Vector<string> $enums = Vector { };
  private Vector<string> $types = Vector { };
  private Vector<string> $newtypes = Vector { };
  private Vector<string> $functions = Vector { };
  private Vector<string> $constants = Vector { };

  private function __construct(
    private string $file,
    string $data,
  ) {
    $this->data = $data;
    $this->consumeFile();
  }

  ///// Constructors /////

  public static function fromFile(
    string $filename,
  ): FileParser {
    return new FileParser(
      $filename,
      file_get_contents($filename),
    );
  }

  public static function fromData(
    string $filename,
    string $data,
  ): FileParser {
    return new FileParser(
      $filename,
      $data,
    );
  }

  ///// Accessors /////

  public function getFilename(): string { return $this->file; }
  public function getClasses(): Vector<string> { return $this->classes; }
  public function getInterfaces(): Vector<string> { return $this->interfaces; }
  public function getTraits(): Vector<string> { return $this->traits; }
  public function getEnums(): Vector<string> { return $this->enums; }
  public function getTypes(): Vector<string> { return $this->types; }
  public function getNewtypes(): Vector<string> { return $this->newtypes; }
  public function getFunctions(): Vector<string> { return $this->functions; }
  public function getConstants(): Vector<string> { return $this->constants; }

  ///// Implementation /////

  private function consumeFile(): void {
    $data = $this->data;
    invariant(
      $data !== null,
      'somehow got constructed with null data for %s',
      $this->file,
    );
    $this->tokens = token_get_all($data);

    while ($this->tokens) {
      $this->skipToCode();
      while ($this->tokens) {
        $token = array_shift($this->tokens);
        if (!is_array($token)) {
          continue;
        }

        $ttype = $token[0];
        if ($ttype === T_CLOSE_TAG) {
          break;
        }
        if (DefinitionToken::isValid($ttype)) {
          $this->consumeDefinition($ttype);
          continue;
        }
        // I hate you, PHP.
        if ($ttype === T_STRING && strtolower($token[1]) === 'define') {
          $this->consumeOldConstantDefinition();
          continue;
        }
      }
    } 
    $this->data = '';
  }

  private function skipToCode(): void {
    $token_type = null;
    do {
      $token = array_shift($this->tokens);
      $token_type = is_array($token) ? $token[0] : null;
    } while ($this->tokens && $token_type !== T_OPEN_TAG);
  }

  private function consumeDefinition(DefinitionToken $def_type): void {
    $tname = token_name($def_type);

    $next = array_shift($this->tokens);
    invariant(
      is_array($next) && $next[0] === T_WHITESPACE,
      'Expect whitespace after %s in %s',
      $tname,
      realpath($this->file),
    );
    if ($def_type === DefinitionToken::NAMESPACE_DEF) {
      $this->consumeNamespaceDefinition();
      return;
    }
    $next = array_shift($this->tokens);
    $next_type = is_array($next) ? $next[0] : null;
    invariant(
      $next_type === T_STRING || $next_type === T_XHP_LABEL,
      'Expected definition name after %s in %s',
      $tname,
      $this->file,
    );
    $name = $next[1];
    if ($next_type === T_XHP_LABEL) {
      invariant(
        $def_type === DefinitionToken::CLASS_DEF,
        '%s is only valid as an XHP class name, not a %s - in %s',
        $name,
        $tname,
        $this->file,
      );
      // 'class :foo:bar' is really 'class xhp_foo__bar'
      $name = 'xhp_'.str_replace(':', '__', substr($name, 1));
    }
    $fqn = $this->namespace.$name;

    switch ($def_type) {
      case DefinitionToken::NAMESPACE_DEF:
        invariant_violation('Should have already consumed namespace :/');
        break;
      case DefinitionToken::CLASS_DEF:
        $this->classes[] = $fqn;
        $this->skipToAndConsumeBlock();
        break;
      case DefinitionToken::INTERFACE_DEF:
        $this->interfaces[] = $fqn;
        $this->skipToAndConsumeBlock();
        break;
      case DefinitionToken::TRAIT_DEF:
        $this->traits[] = $fqn;
        $this->skipToAndConsumeBlock();
        break;
      case DefinitionToken::ENUM_DEF:
        $this->enums[] = $fqn;
        break;
      case DefinitionToken::TYPE_DEF:
        $this->types[] = $fqn;
        break;
      case DefinitionToken::NEWTYPE_DEF:
        $this->newtypes[] = $fqn;
        break;
      case DefinitionToken::FUNCTION_DEF:
        $this->functions[] = $fqn;
        break;
      case DefinitionToken::CONST_DEF:
        $this->consumeConstantDefinition($name);
        break;
    }
  }

  /**
   * /const CONST_NAME =/
   * /const type_name CONST_NAME =/
   *
   * - 'const' and the next token are no longer in $this->tokens
   * - 'first' is either CONST_NAME or type_name. Both are T_STRING
   *
   * Figure out which.
   */
  private function consumeConstantDefinition(string $first): void {
    $name = $first;
    while ($this->tokens) {
      $next = array_shift($this->tokens);
      $next_type = is_array($next) ? $next[0] : null;
      if ($next_type === T_WHITESPACE) {
        continue;
      }
      if ($next_type === T_STRING) {
        // const TYPENAME CONSTNAME = foo
        $name = $next[1];
        continue;
      }
      if ($next === '=') {
        $this->constants[] = $this->namespace.$name;
        return;
      }
    }
  }

  /**
   * define ('FOO', value);
   * define (FOO, value); // yep, this is different. I *REALLY* hate php.
   *
   * 'define' has been consumed, that's it
   */
  private function consumeOldConstantDefinition(): void {
    $this->consumeWhitespace();
    $next = array_shift($this->tokens);
    invariant(
      $next === '(',
      'Expected define to be followed by a paren in %s',
      $this->file,
    );
    $this->consumeWhitespace();
    $next = array_shift($this->tokens);
    $next_type = is_array($next) ? $next[0] : null;
    invariant(
      $next_type === T_CONSTANT_ENCAPSED_STRING || $next_type === T_STRING,
      'Expected arg to define() to be a T_CONSTANT_ENCAPSED_STRING or '.
      'T_STRING, got %s in %s',
      token_name($next_type),
      $this->file,
    );
    $name = $next[1];
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
  }

  private function consumeWhitespace(): void {
    $next = array_shift($this->tokens);
    if (is_array($next) && $next[0] === T_WHITESPACE) {
      return;
    }
    array_unshift($this->tokens, $next);
  }

  private function consumeNamespaceDefinition(): void {
    $parts = [];
    do {
      $this->consumeWhitespace();
      $next = array_shift($this->tokens);
      $next_type = is_array($next) ? $next[0] : null;
      if ($next_type === T_STRING) {
        $parts[] = $next[1];
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
    } while ($this->tokens);

    if ($parts) {
      $this->namespace = implode('\\', $parts).'\\';
    } else {
      $this->namespace = '';
    }
  }

  private function skipToAndConsumeBlock(): void {
    $nesting = 0;
    while ($this->tokens) {
      $next = array_shift($this->tokens);
      if ($next === '{') {
        ++$nesting;
      } else if ($next === '}') {
        --$nesting;
        if ($nesting === 0) {
          return;
        }
      }
    }
  }
}
