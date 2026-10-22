<?php

class ViteHelperTest extends CakeTestCase
{
    private string $dataPath;

    private string $hotFile;

    private View $View;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataPath = dirname(__DIR__, 3) . DS . 'data' . DS;
        $this->hotFile = TMP . 'tests' . DS . 'vite-hot';
        Configure::write('Vite.manifestPath', $this->dataPath . 'manifest.json');
        Configure::write('Vite.hotFile', $this->hotFile);

        $request = new CakeRequest('/', false);
        $request->webroot = '/';
        $this->View = new View(new Controller($request));
    }

    public function tearDown(): void
    {
        Configure::delete('Vite');
        if (is_file($this->hotFile)) {
            unlink($this->hotFile);
        }
        parent::tearDown();
    }

    private function helper(): ViteHelper
    {
        return new ViteHelper($this->View);
    }

    private function startDevServer(string $url): void
    {
        if (!is_dir(dirname($this->hotFile))) {
            mkdir(dirname($this->hotFile), 0777, true);
        }
        file_put_contents($this->hotFile, $url . "\n");
    }

    public function testIsNotDevServerByDefault(): void
    {
        $this->assertFalse($this->helper()->isDevServer());
    }

    public function testEntryScriptTagsWithImports(): void
    {
        $expected = implode("\n\t", [
            '<script type="module" src="/build/assets/app-abc123.js"></script>',
            '<link rel="modulepreload" href="/build/assets/shared-def456.js"/>',
            '<link rel="modulepreload" href="/build/assets/vendor-jkl012.js"/>',
        ]);

        $this->assertSame($expected, $this->helper()->entryScriptTags('assets/app.ts', ['inline' => true]));
    }

    public function testEntryScriptTagsWithNonce(): void
    {
        $expected = implode("\n\t", [
            '<script type="module" src="/build/assets/app-abc123.js" nonce="a&quot;b"></script>',
            '<link rel="modulepreload" href="/build/assets/shared-def456.js" nonce="a&quot;b"/>',
            '<link rel="modulepreload" href="/build/assets/vendor-jkl012.js" nonce="a&quot;b"/>',
        ]);

        $this->assertSame($expected, $this->helper()->entryScriptTags('assets/app.ts', ['inline' => true, 'nonce' => 'a"b']));
    }

    public function testEntryScriptTagsWithNonceOfSecurityHeadersHelper(): void
    {
        $this->View->Helpers->set('SecurityHeaders', new class ($this->View) extends AppHelper {
            public function nonce(): string
            {
                return 'abc';
            }
        });

        $tags = $this->helper()->entryScriptTags('assets/app.ts', ['inline' => true]);
        $this->assertStringContainsString('<script type="module" src="/build/assets/app-abc123.js" nonce="abc"></script>', $tags);
        $this->assertStringContainsString('<link rel="modulepreload" href="/build/assets/shared-def456.js" nonce="abc"/>', $tags);
        $this->assertStringNotContainsString('nonce', $this->helper()->entryScriptTags('assets/app.ts', ['inline' => true, 'nonce' => false]));
        $this->assertStringNotContainsString('nonce', $this->helper()->entryLinkTags('assets/app.ts', ['inline' => true]));
    }

    public function testDevServerScriptTagsWithNonce(): void
    {
        $this->startDevServer('http://localhost:5173');

        $this->assertSame(implode("\n\t", [
            '<script type="module" src="http://localhost:5173/build/@vite/client" nonce="abc"></script>',
            '<script type="module" src="http://localhost:5173/build/assets/app.ts" nonce="abc"></script>',
        ]), $this->helper()->entryScriptTags('assets/app.ts', ['inline' => true, 'nonce' => 'abc']));
    }

    public function testEntryScriptTagsForStylesheetEntryIsEmpty(): void
    {
        $this->assertSame('', $this->helper()->entryScriptTags('assets/style.scss', ['inline' => true]));
    }

    public function testEntryLinkTagsCollectsCssOfImportsWithoutDuplicates(): void
    {
        $expected = implode("\n\t", [
            '<link rel="stylesheet" href="/build/assets/app-ghi789.css"/>',
            '<link rel="stylesheet" href="/build/assets/shared-mno345.css"/>',
        ]);

        $this->assertSame($expected, $this->helper()->entryLinkTags('assets/app.ts', ['inline' => true]));
    }

    public function testEntryLinkTagsForStylesheetEntry(): void
    {
        $this->assertSame(
            '<link rel="stylesheet" href="/build/assets/style-pqr678.css"/>',
            $this->helper()->entryLinkTags('assets/style.scss', ['inline' => true]),
        );
    }

    public function testTagsAreAppendedToDefaultBlocks(): void
    {
        $helper = $this->helper();

        $this->assertNull($helper->entryLinkTags('assets/app.ts'));
        $this->assertNull($helper->entryScriptTags('assets/app.ts'));
        $this->assertStringContainsString('app-ghi789.css', $this->View->fetch('css'));
        $this->assertStringContainsString('app-abc123.js', $this->View->fetch('script'));
    }

    public function testTagsAreAppendedToGivenBlock(): void
    {
        $this->assertNull($this->helper()->entryScriptTags('assets/app.ts', ['block' => 'bottom']));
        $this->assertStringContainsString('app-abc123.js', $this->View->fetch('bottom'));
        $this->assertSame('', $this->View->fetch('script'));
    }

    public function testAsset(): void
    {
        $this->assertSame('/build/assets/logo-stu901.png', $this->helper()->asset('assets/images/logo.png'));
    }

    public function testAssetWithBuildDir(): void
    {
        Configure::write('Vite.buildDir', '/dist/');

        $this->assertSame('/dist/assets/logo-stu901.png', $this->helper()->asset('assets/images/logo.png'));
    }

    public function testImage(): void
    {
        $this->assertSame(
            '<img src="/build/assets/logo-stu901.png" alt="Logo"/>',
            $this->helper()->image('assets/images/logo.png', ['alt' => 'Logo']),
        );
    }

    public function testMissingEntryThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite manifest has no entry: assets/missing.ts');

        $this->helper()->entryScriptTags('assets/missing.ts');
    }

    public function testMissingManifestThrowsException(): void
    {
        Configure::write('Vite.manifestPath', $this->dataPath . 'missing.json');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite manifest file not found');

        $this->helper()->asset('assets/images/logo.png');
    }

    public function testInvalidManifestThrowsException(): void
    {
        Configure::write('Vite.manifestPath', $this->dataPath . 'invalid.json');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Vite manifest.json format');

        $this->helper()->asset('assets/images/logo.png');
    }

    public function testDevServerFromHotFile(): void
    {
        $this->startDevServer('http://localhost:5173/');
        $helper = $this->helper();

        $this->assertTrue($helper->isDevServer());
        $this->assertSame('http://localhost:5173/build/assets/images/logo.png', $helper->asset('assets/images/logo.png'));
    }

    public function testDevServerFromConfigure(): void
    {
        Configure::write('Vite.devServerUrl', 'https://vite.example.com/');

        $this->assertSame('https://vite.example.com/build/assets/app.ts', $this->helper()->asset('assets/app.ts'));
    }

    public function testDevServerScriptTagsIncludeClientOnce(): void
    {
        $this->startDevServer('http://localhost:5173');
        $helper = $this->helper();

        $this->assertSame(implode("\n\t", [
            '<script type="module" src="http://localhost:5173/build/@vite/client"></script>',
            '<script type="module" src="http://localhost:5173/build/assets/app.ts"></script>',
        ]), $helper->entryScriptTags('assets/app.ts', ['inline' => true]));
        $this->assertSame(
            '<script type="module" src="http://localhost:5173/build/assets/admin.ts"></script>',
            $helper->entryScriptTags('assets/admin.ts', ['inline' => true]),
        );
    }

    public function testDevServerLinkTagsOnlyForStylesheetEntry(): void
    {
        $this->startDevServer('http://localhost:5173');
        $helper = $this->helper();

        $this->assertSame('', $helper->entryLinkTags('assets/app.ts', ['inline' => true]));
        $this->assertSame(
            '<link rel="stylesheet" href="http://localhost:5173/build/assets/style.scss"/>',
            $helper->entryLinkTags('assets/style.scss', ['inline' => true]),
        );
    }

    public function testDevServerStylesheetEntryHasOnlyClientScript(): void
    {
        $this->startDevServer('http://localhost:5173');

        $this->assertSame(
            '<script type="module" src="http://localhost:5173/build/@vite/client"></script>',
            $this->helper()->entryScriptTags('assets/style.scss', ['inline' => true]),
        );
    }

    public function testUrlsAreEscaped(): void
    {
        Configure::write('Vite.devServerUrl', 'http://localhost:5173/"onload="x');

        $this->assertStringNotContainsString('"onload="', $this->helper()->entryScriptTags('assets/app.ts', ['inline' => true]));
    }
}
