<?hh // strict

namespace FredEmmott\DefinitionFinder\Expression;

use FredEmmott\DefinitionFinder\TokenQueue;

final class StaticScalarExpression extends Expression {
  protected static function matchImpl(TokenQueue $tq): ?Expression {

    $subtypes = ImmVector {
      CommonScalarExpression::class,
      StaticStringExpression::class,
      StaticClassClassConstantExpression::class,
      AttributeConstantExpression::class,
      PlusMinusStaticNumericScalarExpression::class,
      StaticArrayExpression::class,
      StaticShapeExpression::class,
      /*
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