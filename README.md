# CakePHP 2.x Vite Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/vite?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/vite?label=Packagist)](https://packagist.org/packages/pieceofcake2/vite)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/vite/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/vite/actions/workflows/CI.yml)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/vite/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/vite/CI.yml?label=CI)](https://github.com/pieceofcake2/vite/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/vite?label=Coverage)](https://codecov.io/gh/pieceofcake2/vite)

pieceofcake2/vite integrates [Vite](https://vite.dev/)-built assets into a CakePHP 2
application. Its helper reads Vite's `manifest.json` to output `<link>` and
`<script type="module">` tags with hashed filenames, and can load assets from the Vite dev
server during development.

## Documentation

- Website: https://pieceofcake2.github.io/vite/
- Markdown: [docs/README.md](docs/README.md) (for AI tools and offline reading)

The Markdown documentation is also included in the Composer package, in the plugin's directory: `plugins/Vite/docs/README.md` (the location depends on your `installer-paths`). Install the package with `--dev` if you only need it for development.

## Requirements

* PHP 8.0+
* pieceofcake2/cakephp 2.12+

## Installation

```bash
composer require pieceofcake2/vite
```

Then configure Vite to write a manifest and load the `Vite.Vite` helper.
See [Installation](docs/README.md#installation).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package was created by [pieceofcake2](https://github.com/pieceofcake2), which also maintains
it. It is not a fork of another project and is not based on one.

## License

MIT License. See [LICENSE](LICENSE).
