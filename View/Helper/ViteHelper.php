<?php

App::uses('AppHelper', 'View/Helper');

/**
 * Vite integration helper for CakePHP 2.
 *
 * Reads Vite's `manifest.json` to output tags for built assets, or points
 * to the Vite dev server when `Vite.devServerUrl` is configured.
 *
 * @property HtmlHelper $Html
 */
class ViteHelper extends AppHelper
{
    public $helpers = ['Html'];

    /**
     * Full filesystem path to Vite's manifest.json.
     */
    protected string $manifestPath;

    /**
     * Public base directory of built assets (Vite's `base`, relative to webroot).
     */
    protected string $buildDir;

    /**
     * Vite dev server URL. Empty when using built assets.
     */
    protected string $devServerUrl;

    /**
     * Cached manifest data.
     */
    protected ?array $manifest = null;

    /**
     * Whether the Vite client script has already been output.
     */
    protected bool $clientIncluded = false;

    /**
     * Constructor: reads settings from Configure, or uses defaults.
     */
    public function __construct(View $View, $settings = [])
    {
        parent::__construct($View, $settings);

        $this->manifestPath = Configure::read('Vite.manifestPath')
            ?: WWW_ROOT . 'build/manifest.json';
        $this->buildDir = trim(Configure::read('Vite.buildDir') ?: 'build', '/') . '/';
        $this->devServerUrl = rtrim((string)Configure::read('Vite.devServerUrl'), '/');
    }

    /**
     * Whether assets are served from the Vite dev server.
     */
    public function isDevServer(): bool
    {
        return $this->devServerUrl !== '';
    }

    /**
     * Load and cache the manifest.json data.
     *
     * @throws RuntimeException
     */
    protected function loadManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!file_exists($this->manifestPath)) {
            throw new RuntimeException("Vite manifest file not found: {$this->manifestPath}");
        }
        $data = json_decode(file_get_contents($this->manifestPath), true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
            throw new RuntimeException('Invalid Vite manifest.json format');
        }

        return $this->manifest = $data;
    }

    /**
     * Get a manifest chunk by its source path (e.g. 'assets/app.ts').
     *
     * @throws RuntimeException
     */
    protected function chunk(string $name): array
    {
        $manifest = $this->loadManifest();
        if (!isset($manifest[$name])) {
            throw new RuntimeException("Vite manifest has no entry: {$name}");
        }

        return $manifest[$name];
    }

    /**
     * Collect the chunk keys statically imported by the given chunk, recursively.
     */
    protected function importedChunks(string $name, array &$seen = []): array
    {
        foreach ($this->chunk($name)['imports'] ?? [] as $import) {
            if (!isset($seen[$import])) {
                $seen[$import] = true;
                $this->importedChunks($import, $seen);
            }
        }

        return array_keys($seen);
    }

    /**
     * Build the URL of a built file (path relative to the build directory).
     *
     * Built filenames are already hashed, so no asset timestamp is appended.
     */
    protected function buildUrl(string $file): string
    {
        return $this->webroot($this->buildDir . $file);
    }

    /**
     * Build the URL of a source file on the dev server.
     *
     * The dev server also serves under Vite's `base`, so the build directory is prepended.
     */
    protected function devUrl(string $path): string
    {
        return $this->devServerUrl . '/' . $this->buildDir . ltrim($path, '/');
    }

    /**
     * Whether the given path is a stylesheet.
     */
    protected function isCss(string $path): bool
    {
        return (bool)preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)(\?|$)/', $path);
    }

    /**
     * Output tags either inline or into a view block.
     *
     * - `block` Name of the view block to append to.
     * - `inline` Set to false to append to the default block. Defaults to false.
     */
    protected function renderTags(array $tags, array $options, string $defaultBlock): ?string
    {
        $options += ['inline' => false, 'block' => null];
        if (!$options['inline'] && empty($options['block'])) {
            $options['block'] = $defaultBlock;
        }

        $out = implode("\n\t", $tags);
        if (empty($options['block'])) {
            return $out;
        }
        if ($out !== '') {
            $this->_View->append($options['block'], $out);
        }

        return null;
    }

    /**
     * Render a <link> tag.
     */
    protected function linkTag(string $rel, string $url): string
    {
        return sprintf('<link rel="%s" href="%s"/>', $rel, h($url));
    }

    /**
     * Render a module <script> tag.
     */
    protected function moduleScriptTag(string $url): string
    {
        return sprintf('<script type="module" src="%s"></script>', h($url));
    }

    /**
     * Render <link> tags for the CSS of the given entry and its imports.
     *
     * In dev server mode, styles are injected by Vite, so only stylesheet entries are output.
     *
     * @param string $entry Entry source path as in manifest.json (e.g. 'assets/app.ts')
     * @param array $options `block` / `inline` options
     * @return string|null Tags when output inline, otherwise null
     */
    public function entryLinkTags(string $entry, array $options = []): ?string
    {
        $tags = [];
        if ($this->isDevServer()) {
            if ($this->isCss($entry)) {
                $tags[] = $this->linkTag('stylesheet', $this->devUrl($entry));
            }

            return $this->renderTags($tags, $options, 'css');
        }

        $files = [];
        foreach (array_merge([$entry], $this->importedChunks($entry)) as $name) {
            $chunk = $this->chunk($name);
            if ($this->isCss($chunk['file'])) {
                $files[] = $chunk['file'];
            }
            foreach ($chunk['css'] ?? [] as $css) {
                $files[] = $css;
            }
        }
        foreach (array_unique($files) as $file) {
            $tags[] = $this->linkTag('stylesheet', $this->buildUrl($file));
        }

        return $this->renderTags($tags, $options, 'css');
    }

    /**
     * Render module <script> tags (and modulepreload links) for the given entry.
     *
     * In dev server mode, the Vite client and the entry source are loaded from the dev server.
     *
     * @param string $entry Entry source path as in manifest.json (e.g. 'assets/app.ts')
     * @param array $options `block` / `inline` options
     * @return string|null Tags when output inline, otherwise null
     */
    public function entryScriptTags(string $entry, array $options = []): ?string
    {
        $tags = [];
        if ($this->isDevServer()) {
            if (!$this->clientIncluded) {
                $tags[] = $this->moduleScriptTag($this->devUrl('@vite/client'));
                $this->clientIncluded = true;
            }
            if (!$this->isCss($entry)) {
                $tags[] = $this->moduleScriptTag($this->devUrl($entry));
            }

            return $this->renderTags($tags, $options, 'script');
        }

        $file = $this->chunk($entry)['file'];
        if (!$this->isCss($file)) {
            $tags[] = $this->moduleScriptTag($this->buildUrl($file));
            foreach ($this->importedChunks($entry) as $name) {
                $tags[] = $this->linkTag('modulepreload', $this->buildUrl($this->chunk($name)['file']));
            }
        }

        return $this->renderTags($tags, $options, 'script');
    }

    /**
     * Resolve a source asset path to its URL via manifest.json.
     * E.g. asset('assets/images/logo.png') => '/build/assets/logo-3eed42ab.png'.
     *
     * @param string $path Asset source path as in manifest.json
     * @return string
     */
    public function asset(string $path): string
    {
        if ($this->isDevServer()) {
            return $this->devUrl($path);
        }

        return $this->buildUrl($this->chunk($path)['file']);
    }

    /**
     * Render an <img> tag for an asset resolved via manifest.json.
     *
     * @param string $path Asset source path as in manifest.json
     * @param array $htmlAttributes Additional HTML attributes for the <img> tag
     * @return string
     */
    public function image(string $path, array $htmlAttributes = []): string
    {
        return $this->Html->image($this->asset($path), $htmlAttributes);
    }
}
