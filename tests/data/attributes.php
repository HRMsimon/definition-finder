<?hh // strict

namespace FredEmmott\DefinitionFinder\Test;

<<Foo>>
class ClassWithSimpleAttribute {}

<<Foo,Bar>>
class ClassWithSimpleAttributes {}

<<Herp('derp')>>
class ClassWithStringAttribute {}

<<Herp(123)>>
class ClassWithIntAttribute {}

<<Foo('bar','baz')>>
class ClassWithMultipleAttributeValues {}

<<FunctionFoo>>
function function_after_classes(): void {}

<<ClassFoo>>
class ClassAfterFunction {}

<<
Foo,
Bar
(
    'herp',
    'derp',
)
>>
class ClassWithFormattedAttributes {}

<<
Bar(
  ['herp']
)
>>
class ClassWithFormattedArrayAttribute {}
