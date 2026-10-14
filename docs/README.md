# Vite Documentation

pieceofcake2/vite provides a view helper that integrates [Vite](https://vite.dev/)-built
assets into a CakePHP 2 application. It reads Vite's `manifest.json` to output `<link>` and
`<script type="module">` tags with hashed filenames, and can load assets from the Vite dev
server during development.

- [Installation](#installation)
- [Configuration](#configuration)
- [Helper Methods](#helper-methods)
- [Dev Server](#dev-server)

## Installation

1. **Install the plugin** with [Composer](https://getcomposer.org/):

   ```bash
   composer require pieceofcake2/vite
   ```

   The plugin has the `cakephp-plugin` type, so it is installed into your plugins directory
   by `composer/installers`. Set `installer-paths` in your application's `composer.json` so
   it is installed into the right place:

   | Layout | `installer-paths` |
   | --- | --- |
   | Modern (`config/`, `src/`, `templates/`, `plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
   | Legacy (`app/Config/`, `app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

   Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`).
   If you already use `CakePlugin::loadAll()`, this is not necessary:

   ```php
   CakePlugin::load('Vite');
   ```

2. **Configure Vite** to write a manifest into the `build/` directory of your webroot
   (`webroot/build/`, legacy layout: `app/webroot/build/`):

   ```js
   // vite.config.js
   import { defineConfig } from 'vite';

   export default defineConfig({
       base: '/build/',
       publicDir: false,
       build: {
           outDir: 'webroot/build', // legacy layout: 'app/webroot/build'
           emptyOutDir: true,
           manifest: 'manifest.json',
           rollupOptions: {
               input: 'assets/app.ts',
           },
       },
   });
   ```

   Running `vite build` generates `webroot/build/manifest.json` and the built files under
   `webroot/build/assets/`.

3. **Load the helper** in your controller (e.g. `AppController`):

   ```php
   class AppController extends Controller
   {
       public $helpers = ['Html', 'Vite.Vite'];
   }
   ```

   Now you can use `$this->Vite` in your views.

## Configuration

All settings are optional. Write them in `config/bootstrap.php` (legacy layout:
`app/Config/bootstrap.php`):

```php
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

Entries are specified by their source path, as used for the keys of `manifest.json`
(e.g. `assets/app.ts`).

### `entryLinkTags($entry, array $options = [])`

Renders `<link rel="stylesheet">` tags for the CSS of the entry and all of its statically
imported chunks.

### `entryScriptTags($entry, array $options = [])`

Renders a `<script type="module">` tag for the entry, and `<link rel="modulepreload">` tags
for its imported chunks.

Both methods append the tags to a view block by default (`css` and `script` respectively),
like `HtmlHelper` with `'inline' => false`. Pass `['block' => 'name']` to choose the block,
or `['inline' => true]` to get the tags as a string.

In your layout, e.g. `templates/Layouts/default.ctp` (legacy layout:
`app/View/Layouts/default.ctp`):

```php
<head>
    <?php $this->Vite->entryLinkTags('assets/app.ts'); ?>
    <?php $this->Vite->entryScriptTags('assets/app.ts'); ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
```

### `asset($path)` / `image($path, array $htmlAttributes = [])`

Resolves a source asset path to its built URL via `manifest.json`, or renders an `<img>` tag
for it. The asset must be included in the manifest (e.g. as a build input).

```php
echo $this->Vite->image('assets/images/logo.png', ['alt' => 'Logo']);
```

### `isDevServer()`

Returns whether assets are loaded from the Vite dev server (see below).

## Dev Server

While the hot file (`webroot/hot` by default, legacy layout: `app/webroot/hot`) exists, the
helper loads assets from the dev server URL written in it: `entryScriptTags()` outputs the
Vite client and the entry source (under `Vite.buildDir`, as the dev server also serves under
Vite's `base`), and `entryLinkTags()` outputs nothing for script entries since Vite injects
the styles. Without the hot file, built assets are loaded via `manifest.json`, so no
configuration is needed to switch between them.

Write the hot file from your Vite config while the dev server is running, and allow requests
from the CakePHP origin:

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
    plugins: [hotFile('webroot/hot', devServerUrl)], // legacy layout: 'app/webroot/hot'
    server: {
        port: 5173,
        strictPort: true,
        cors: true,
        origin: devServerUrl,
    },
});
```

Add the hot file to `.gitignore` so it is never committed or deployed.
