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
