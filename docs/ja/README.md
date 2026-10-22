# Vite ドキュメント

pieceofcake2/vite は、[Vite](https://vite.dev/) でビルドしたアセットを CakePHP 2 アプリケーションに
組み込むためのビューヘルパーを提供します。Vite の `manifest.json` を読み込んで、ハッシュ付きのファイル名で
`<link>` タグと `<script type="module">` タグを出力します。開発中は Vite の開発サーバーからアセットを
読み込むこともできます。

- [インストール](#installation)
- [設定](#configuration)
- [ヘルパーのメソッド](#helper-methods)
- [開発サーバー](#dev-server)

<a id="installation"></a>

## インストール

1. [Composer](https://getcomposer.org/) で**プラグインをインストール**します。

   ```bash
   composer require pieceofcake2/vite
   ```

   このプラグインは `cakephp-plugin` タイプなので、`composer/installers` によってプラグインディレクトリに
   インストールされます。正しい場所にインストールされるように、アプリケーションの `composer.json` で
   `installer-paths` を設定してください。

   | レイアウト | `installer-paths` |
   | --- | --- |
   | モダン (`config/`, `src/`, `templates/`, `plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
   | レガシー (`app/Config/`, `app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

   `config/bootstrap.php` (レガシーレイアウトでは `app/Config/bootstrap.php`) でプラグインを読み込みます。
   すでに `CakePlugin::loadAll()` を使っている場合は不要です。

   ```php
   CakePlugin::load('Vite');
   ```

2. webroot の `build/` ディレクトリ (`webroot/build/`、レガシーレイアウトでは `app/webroot/build/`) に
   マニフェストを書き出すように **Vite を設定**します。

   ```js
   // vite.config.js
   import { defineConfig } from 'vite';

   export default defineConfig({
       base: '/build/',
       publicDir: false,
       build: {
           outDir: 'webroot/build', // レガシーレイアウト: 'app/webroot/build'
           emptyOutDir: true,
           manifest: 'manifest.json',
           rollupOptions: {
               input: 'assets/app.ts',
           },
       },
   });
   ```

   `vite build` を実行すると、`webroot/build/manifest.json` と、`webroot/build/assets/` 以下に
   ビルドされたファイルが生成されます。

3. コントローラー (たとえば `AppController`) で**ヘルパーを読み込み**ます。

   ```php
   class AppController extends Controller
   {
       public $helpers = ['Html', 'Vite.Vite'];
   }
   ```

   これでビューから `$this->Vite` を使えるようになります。

<a id="configuration"></a>

## 設定

設定はすべて省略可能です。`config/bootstrap.php` (レガシーレイアウトでは `app/Config/bootstrap.php`)
に記述します。

```php
// manifest.json のファイルシステム上のフルパス (デフォルト: WWW_ROOT . 'build/manifest.json')
Configure::write('Vite.manifestPath', WWW_ROOT . 'build/manifest.json');
// webroot からの相対パスで表したビルド済みファイルのディレクトリ。Vite の `base` に合わせます (デフォルト: 'build')
Configure::write('Vite.buildDir', 'build');
// 開発サーバーの実行中に書き出される hot ファイルのファイルシステム上のフルパス (デフォルト: WWW_ROOT . 'hot')
Configure::write('Vite.hotFile', WWW_ROOT . 'hot');
// Vite の開発サーバーの URL。hot ファイルの有無にかかわらず開発サーバーを使います
Configure::write('Vite.devServerUrl', 'http://localhost:5173');
```

<a id="helper-methods"></a>

## ヘルパーのメソッド

エントリーは、`manifest.json` のキーに使われているソースのパス (たとえば `assets/app.ts`) で指定します。

<a id="entrylinktagsentry-array-options--"></a>

### `entryLinkTags($entry, array $options = [])`

エントリーと、そこから静的にインポートされるすべてのチャンクの CSS について、`<link rel="stylesheet">`
タグを出力します。

<a id="entryscripttagsentry-array-options--"></a>

### `entryScriptTags($entry, array $options = [])`

エントリーの `<script type="module">` タグと、インポートされるチャンクの `<link rel="modulepreload">`
タグを出力します。

どちらのメソッドも、`'inline' => false` を指定した `HtmlHelper` と同じように、デフォルトではタグを
ビューブロック (それぞれ `css` と `script`) に追加します。ブロックを選ぶには `['block' => 'name']` を、
タグを文字列として受け取るには `['inline' => true]` を渡してください。

レイアウト、たとえば `templates/Layouts/default.ctp` (レガシーレイアウトでは
`app/View/Layouts/default.ctp`) では次のようにします。

```php
<head>
    <?php $this->Vite->entryLinkTags('assets/app.ts'); ?>
    <?php $this->Vite->entryScriptTags('assets/app.ts'); ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
```

#### Content Security Policy の nonce

nonce でスクリプトを許可する Content Security Policy (`'strict-dynamic'` など) では、`entryScriptTags()` は
`<script>` と `<link rel="modulepreload">` のタグに nonce を付けます。ビューが
[pieceofcake2/security-headers](https://github.com/pieceofcake2/security-headers) の `SecurityHeaders`
ヘルパーを読み込んでいれば、そのヘルパーの nonce を使います。そうでなければ `nonce` オプションで渡します。
`'nonce' => false` で付けません。

```php
<?php $this->Vite->entryScriptTags('assets/app.ts', ['nonce' => $nonce]); ?>
```

`entryLinkTags()` の `<link rel="stylesheet">` タグには nonce を付けません。

<a id="assetpath--imagepath-array-htmlattributes--"></a>

### `asset($path)` / `image($path, array $htmlAttributes = [])`

ソースのアセットのパスを `manifest.json` を使ってビルド後の URL に解決するか、そのアセットの `<img>`
タグを出力します。アセットはマニフェストに含まれている (たとえばビルドの入力に指定されている) 必要があります。

```php
echo $this->Vite->image('assets/images/logo.png', ['alt' => 'Logo']);
```

<a id="isdevserver"></a>

### `isDevServer()`

アセットを Vite の開発サーバーから読み込んでいるかどうかを返します (下記参照)。

<a id="dev-server"></a>

## 開発サーバー

hot ファイル (デフォルトでは `webroot/hot`、レガシーレイアウトでは `app/webroot/hot`) が存在する間は、
ヘルパーはそこに書かれた開発サーバーの URL からアセットを読み込みます。`entryScriptTags()` は Vite
クライアントとエントリーのソースを出力し (開発サーバーも Vite の `base` 以下で配信するので、
`Vite.buildDir` 以下のパスになります)、`entryLinkTags()` はスクリプトのエントリーについては何も
出力しません (スタイルは Vite が挿入します)。hot ファイルがなければ、ビルドされたアセットを
`manifest.json` を使って読み込みます。そのため、両者を切り替えるための設定は必要ありません。

開発サーバーの実行中に Vite の設定から hot ファイルを書き出し、CakePHP のオリジンからのリクエストを
許可してください。

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
    plugins: [hotFile('webroot/hot', devServerUrl)], // レガシーレイアウト: 'app/webroot/hot'
    server: {
        port: 5173,
        strictPort: true,
        cors: true,
        origin: devServerUrl,
    },
});
```

hot ファイルがコミットやデプロイされないように、`.gitignore` に追加してください。

<a id="origin"></a>

## 由来

このパッケージは [pieceofcake2](https://github.com/pieceofcake2) が作成し、メンテナンスしています。
他のプロジェクトのフォークではなく、他のプロジェクトをもとにしたものでもありません。
