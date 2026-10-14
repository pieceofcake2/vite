# CakePHP 2.x Vite Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/vite?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/vite?label=Packagist)](https://packagist.org/packages/pieceofcake2/vite)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/vite/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/vite/actions/workflows/CI.yml)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/vite/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/vite/CI.yml?label=CI)](https://github.com/pieceofcake2/vite/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/vite?label=Coverage)](https://codecov.io/gh/pieceofcake2/vite)

This helper integrates [Vite](https://vite.dev/)-built assets into a CakePHP 2 application. It reads Vite's `manifest.json` to output `<link>` and `<script type="module">` tags with hashed filenames, and can load assets from the Vite dev server during development.

## Installation

1. **Install the plugin**:
   ```bash
   composer require pieceofcake2/vite
   ```

2. **Configure Vite** to write a manifest into `webroot/build/`:
   ```js
   // vite.config.js
   import { defineConfig } from 'vite';

   export default defineConfig({
       base: '/build/',
       publicDir: false,
       build: {
           outDir: 'webroot/build',
           emptyOutDir: true,
           manifest: 'manifest.json',
           rollupOptions: {
               input: 'assets/app.ts',
           },
       },
   });
   ```
   Running `vite build` generates `webroot/build/manifest.json` and the built files under `webroot/build/assets/`.

3. **Load the helper** in your controller (e.g. `AppController`):
   ```php
   class AppController extends Controller {
       public $helpers = ['Html', 'Vite.Vite'];
   }
   ```
   Now you can use `$this->Vite` in your views.

## Configuration (Configure)

All settings are optional.

```php
// app/Config/bootstrap.php

// Full filesystem path to manifest.json (default: WWW_ROOT . 'build/manifest.json')
Configure::write('Vite.manifestPath', WWW_ROOT . 'build/manifest.json');
// Directory of built files relative to webroot, matching Vite's `base` (default: 'build')
Configure::write('Vite.buildDir', 'build');
// Full filesystem path to the hot file written while the dev server is running (default: WWW_ROOT . 'hot')
Configure::write('Vite.hotFile', WWW_ROOT . 'hot');
// Vite dev server URL. Forces the dev server regardless of the hot file
Configure::write('Vite.devServerUrl', 'http://localhost:5173');
```

## Helper Methods

Entries are specified by their source path, as used for the keys of `manifest.json` (e.g. `assets/app.ts`).

### `entryLinkTags($entry, array $options = [])`

Renders `<link rel="stylesheet">` tags for the CSS of the entry and all of its statically imported chunks.

### `entryScriptTags($entry, array $options = [])`

Renders a `<script type="module">` tag for the entry, and `<link rel="modulepreload">` tags for its imported chunks.

Both methods append the tags to a view block by default (`css` and `script` respectively), like `HtmlHelper` with `'inline' => false`. Pass `['block' => 'name']` to choose the block, or `['inline' => true]` to get the tags as a string.

```php
<head>
    <?php $this->Vite->entryLinkTags('assets/app.ts'); ?>
    <?php $this->Vite->entryScriptTags('assets/app.ts'); ?>
    <?php echo $this->fetch('css'); ?>
    <?php echo $this->fetch('script'); ?>
</head>
```

### `asset($path)` / `image($path, array $htmlAttributes = [])`

Resolves a source asset path to its built URL via `manifest.json`, or renders an `<img>` tag for it. The asset must be included in the manifest (e.g. as a build input).

```php
echo $this->Vite->image('assets/images/logo.png', ['alt' => 'Logo']);
```

## Dev Server

While the hot file (`webroot/hot` by default) exists, the helper loads assets from the dev server URL written in it: `entryScriptTags()` outputs the Vite client and the entry source (under `Vite.buildDir`, as the dev server also serves under Vite's `base`), and `entryLinkTags()` outputs nothing for script entries since Vite injects the styles. Without the hot file, built assets are loaded via `manifest.json`, so no configuration is needed to switch between them.

Write the hot file from your Vite config while the dev server is running, and allow requests from the CakePHP origin:

```js
// vite.config.js
import { rmSync, writeFileSync } from 'node:fs';
import { defineConfig } from 'vite';

const devServerUrl = 'http://localhost:5173';

const hotFile = (path, url) => ({
    name: 'hot-file',
    apply: 'serve',
    configureServer(server) {
        const clean = () => rmSync(path, { force: true });
        server.httpServer?.once('listening', () => writeFileSync(path, url));
        process.once('exit', clean);
        for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
            process.once(signal, () => process.exit());
        }
    },
});

export default defineConfig({
    // ...
    plugins: [hotFile('webroot/hot', devServerUrl)],
    server: {
        port: 5173,
        strictPort: true,
        cors: true,
        origin: devServerUrl,
    },
});
```

Add the hot file to `.gitignore` so it is never committed or deployed.

## License

This plugin is released under the MIT License.
