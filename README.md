# Depict

*A fast, recursion-safe, and succinct replacement for var_dump/var_export/print_r.*

[![Current version image][version-image]][current version]
[![Current build status image][build-image]][current build status]
[![Current Windows build status image][windows-build-image]][current windows build status]
[![Tested against HHVM][hhvm-image]][current hhvm build status]
[![Current coverage status image][coverage-image]][current coverage status]

[build-image]: https://img.shields.io/travis/eloquent/depict/master.svg?style=flat-square "Current build status for the master branch"
[coverage-image]: https://img.shields.io/codecov/c/github/eloquent/depict/master.svg?style=flat-square "Current test coverage for the master branch"
[current build status]: https://travis-ci.org/eloquent/depict
[current coverage status]: https://codecov.io/github/eloquent/depict
[current hhvm build status]: https://travis-ci.org/eloquent/depict
[current version]: https://packagist.org/packages/eloquent/depict
[current windows build status]: https://ci.appveyor.com/project/eloquent/depict
[hhvm-image]: https://img.shields.io/badge/hhvm-tested-brightgreen.svg?style=flat-square "Tested against HHVM"
[version-image]: https://img.shields.io/packagist/v/eloquent/depict.svg?style=flat-square "This project uses semantic versioning"
[windows-build-image]: https://img.shields.io/appveyor/ci/eloquent/depict/master.svg?label=windows&style=flat-square "Current Windows build status for the master branch"

## Installation

- Available as [Composer] package [eloquent/depict].

[composer]: http://getcomposer.org/
[eloquent/depict]: https://packagist.org/packages/eloquent/depict

## Usage

```php
use Eloquent\Depict\InlineExporter;

$exporter = InlineExporter::create();
echo $exporter->export(['a', 'b', 'c', 1, 2, 3]); // outputs '#0["a", "b", "c", 1, 2, 3]'
```

## What is *Depict*?

*Depict* is a `var_dump()`/`var_export()`/`print_r()` replacement that focuses
on performance, succinctness, and recursion safety. In contrast to many of the
available libraries providing similar functionality, *Depict* is designed
specifically for command line output (not HTML).

*Depict* is based on the [exporter used by Phony], and is essentially the same
code, with the Phony-specific parts removed.

[exporter used by phony]: http://eloquent-software.com/phony/latest/#the-exporter

## Exporters

*Depict* currently implements only one exporter, the [`InlineExporter`]. More
exporters may be implemented in future. Exporters implement a very simple
[`Exporter`] interface.

[`exporter`]: src/Exporter.php
[`inlineexporter`]: #inlineexporter

### `InlineExporter`

This exporter is designed for single-line output of values. To create an inline
exporter, use `InlineExporter::create()`:

```php
$exporter = InlineExporter::create();
```

`InlineExporter::create()` can also accept an options array to customize its
output:

```php
$exporter = InlineExporter::create(['depth' => 1]);
```

Available options for `InlineExporter::create()`:

Option          | Description                                                                                                                                            | Default
----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|--------
`depth`         | An integer that determines the depth to which Depict will export before truncating output. Negative values are treated as infinite depth.              | `-1`
`breadth`       | An integer that determines the number of sub-values that Depict will export before truncating output. Negative values are treated as infinite breadth. | `-1`
`useShortNames` | When `true`, Depict will omit namespace information from exported symbol names.                                                                        | `true`
`useShortPaths` | When `true`, Depict will export only the basename of closure paths.                                                                                    | `false`

## The export format

Depict generates a concise, unambiguous, human-readable representation of any
PHP value, including recursive objects and arrays:

Input value                     | Exporter output
--------------------------------|-----------------
`null`                          | `'null'`
`true`                          | `'true'`
`false`                         | `'false'`
`111`                           | `'111'`
`1.11`                          | `'1.110000e+0'`
`'1.11'`                        | `'"1.11"'`
`"a\nb"`                        | `'"a\nb"'`
`STDIN`                         | `'resource#1'`
`[1, 2]`                        | `'#0[1, 2]'`
`['a' => 1, 'b' => 2]`          | `'#0["a": 1, "b": 2]`
`(object) ['a' => 1, 'b' => 2]` | `'#0{a: 1, b: 2}'`
`new ClassA()`                  | `'ClassA#0{}'`

### Export identifiers and references

Exported arrays, and objects include a numeric identifier that can be used to
identify repeated occurrences of the same value. This is represented as a number
sign (`#`) followed by the identifier:

```php
$value = (object) array();
// $value is exported as '#0{}'
```

When a value appears multiple times, its internal structure will only be
described the first time. Subsequent appearances will be indicated by a
reference to the value's identifier. This is represented as an ampersand (`&`)
followed by the identifier:

```php
$inner = [1, 2];
$value = [&$inner, &$inner];
// $value is exported as '#0[#1[1, 2], &1[]]'

$inner = (object) ['a' => 1];
$value = (object) ['b' => $inner, 'c' => $inner];
// $value is exported as '#0{b: #1{a: 1}, c: &1{}}'
```

#### Export reference types

Array references appear followed by brackets (e.g. `&0[]`), and object
references appear followed by braces (e.g. `&0{}`):

```php
$array = [];
$object = (object) [];

$value = [&$array, &$array];
// $value is exported as '#0[#1[], &1[]]'

$value = [$object, $object];
// $value is exported as '#0[#0{}, &0{}]'
```

This is necessary in order to disambiguate references, because arrays and other
types can sometimes have the same identifier:

```php
$value = [
    (object) [],
    [
        (object) [],
    ],
];
// $value is exported as '#0[#0{}, #1[#1{}]]'
```

#### Export reference exclusions

As well as excluding the content, object references exclude the class name, for
brevity:

```php
$inner = new ClassA();
$inner->c = "d";
$value = (object) ['a' => $inner, 'b' => $inner];
// $value is exported as '#0{a: ClassA#1{c: "d"}, b: &1{}}'
```

#### Export identifier persistence

Identifiers for objects are persistent across invocations of an exporter, and
share a single sequence of numbers:

```php
$a = (object) [];
$b = (object) [];

$value = [$a, $b, $a];
// $value is exported as '#0[#0{}, #1{}, &0{}]'

$value = [$b, $a, $b];
// $value is exported as '#0[#1{}, #0{}, &1{}]'
```

But due to PHP's limitations, array identifiers are only persistent within a
single exporter invocation:

```php
$a = [];
$b = [];

$valueA = [&$a, &$b, &$a];
$valueB = [&$b, &$a, &$b];
// both $valueA and $valueB are exported as '#0[#1[], #2[], &1[]]'
```

### Exporting recursive values

If a recursive value is exported, the points of recursion are exported as
[references], in the same way that multiple instances of the same value are
handled:

```php
$value = [];
$value[] = &$value;
// $value is exported as '#0[&0[]]'

$value = (object) [];
$value->a = $value;
// $value is exported as '#0{a: &0{}}'
```

### Exporter special cases

For certain types of values, exporters will exhibit special behavior, in order
to improve the usefulness of its output, or to improve performance in common use
cases.

#### Exporting closures

When a closure is exported, the file path and start line number are included in
the output:

```php
$closure = function () {}; // file path is /path/to/example.php, line number is 123
// $closure is exported as 'Closure#0{}[/path/to/example.php:123]'
```

Note that the class name will always be exported as `Closure`, even for runtimes
such as [HHVM] that use different class names for closures.

[hhvm]: http://hhvm.com/

#### Exporting exceptions

When an exception is exported, some internal PHP details are stripped from the
output, including file path, line number, and stack trace:

```php
$exception = new Exception('a', 1, new Exception());
// $exception is exported as 'Exception#0{message: "a", code: 1, previous: Exception#1{}}'
```

Additionally, when the message is `''`, the code is `0`, and/or the previous
exception is `null`, these values are excluded for brevity:

```php
$exception = new RuntimeException();
// $exception is exported as 'RuntimeException#0{}'
```

## Export depth and breadth

For complicated nested structures, exporting the entire structure is not always
desirable. *Depict* can set limits on how much *depth* and *breadth* of the
supplied value it will export.

When a value is beyond the export depth, and has sub-values, its contents will
be replaced with a special notation that simply indicates how many sub-values
exist within that value:

```php
$value = [[], ['a', 'b', 'c']];
// with a depth of 1, $value is exported as '#0[#1[], #2[~3]]'

$value = [(object) [], (object) ['a', 'b', 'c']];
// with a depth of 1, $value is exported as '#0[#0{}, #1{~3}]'
```

When the breadth limit of a value has been reached, a similar notation is used
to indicate how many sub-values remain unexported:

```php
$value = ['a', 'b', 'c', 'd', 'e'];
// with a breadth of 2, $value is exported as '#0["a", "b", ~3]'
```
