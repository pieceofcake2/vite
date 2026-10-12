# CakePHP 2.x Vite Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/vite?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/vite?label=Packagist)](https://packagist.org/packages/pieceofcake2/vite)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/vite/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/vite/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)

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
// Vite dev server URL. When set, assets are loaded from the dev server instead of manifest.json
Configure::write('Vite.devServerUrl', env('VITE_DEV_SERVER_URL'));
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

When `Vite.devServerUrl` is set (e.g. `http://localhost:5173`), `entryScriptTags()` outputs the Vite client and the entry source from the dev server, and `entryLinkTags()` outputs nothing for script entries since Vite injects the styles. Configure the dev server to allow requests from the CakePHP origin:

```js
server: {
    cors: true,
    origin: 'http://localhost:5173',
},
```

## License

This plugin is released under the MIT License.
