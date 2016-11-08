<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticScalarExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {

    $subtypes = ImmVector {
      CommonScalarExpression::class,
      StaticStringExpression::class,
      /*
  | static_class_class_constant        { $$ = $1;}
  | fully_qualified_class_name
    T_DOUBLE_COLON
    T_STRING                           { HPHP_PARSER_ERROR("User-defined "
                                        "constants are not allowed in "
                                        "user attribute expressions", _p);}
  | ident_no_semireserved              { constant_ae(_p,$$,$1);}
  | '+' static_numeric_scalar_ae       { UEXP($$,$2,'+',1);}
  | '-' static_numeric_scalar_ae       { UEXP($$,$2,'-',1);}
  | T_ARRAY '('
    static_array_pair_list_ae ')'      { _p->onArray($$,$3,T_ARRAY);}
  | '[' static_array_pair_list_ae ']'  { _p->onArray($$,$2,T_ARRAY);}
  | T_SHAPE '('
    static_shape_pair_list_ae ')'      { _p->onArray($$,$3,T_ARRAY); }
  | static_dict_literal_ae             { $$ = $1;}
  | static_vec_literal_ae              { $$ = $1;}
  | static_keyset_literal_ae           { $$ = $1;}
      */
    };
    foreach ($subtypes as $subtype) {
      $match = $subtype::match($tq);
      if ($match) {
        return $match;
      }
    }
    return null;
  }
}