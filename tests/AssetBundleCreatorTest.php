<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Webpack\Manifest\Tests;

use BeastBytes\Webpack\Manifest\AssetBundleCreator;
use PHPUnit\Framework\TestCase;
use Yiisoft\Aliases\Aliases;
use Yiisoft\View\WebView;

use const DIRECTORY_SEPARATOR;

class AssetBundleCreatorTest extends TestCase
{
    private const DEFAULTS = [
        'basePath' => null,
        'baseUrl' => '/',
        'cssOptions' => [],
        'cssPosition' => null,
        'jsOptions' => [],
        'jsPosition' => null,
        'manifest' => AssetBundleCreator::DEFAULT_MANIFEST,
        'manifestPath' => null,
        'className' => AssetBundleCreator::DEFAULT_CLASS
    ];

    private static Aliases $aliases;
    private AssetBundleCreator $creator;
    private string $filename = AssetBundleCreator::DEFAULT_CLASS;

    protected function setUp(): void
    {
        self::$aliases = new Aliases([
            'app' => __DIR__ . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'assetBundle',
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'basePath',
            'manifestPath' => __DIR__ . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'manifest',
        ]);

        $this->creator = new AssetBundleCreator(self::$aliases);
    }
    protected function tearDown(): void
    {
        unlink(
            self::$aliases->get('@app')
            . DIRECTORY_SEPARATOR . 'Assets'
            . DIRECTORY_SEPARATOR . $this->filename . '.php'
        );
    }

    public static function tearDownAfterClass(): void
    {
        $dir = self::$aliases->get('@app');
        rmdir($dir . DIRECTORY_SEPARATOR . 'Assets');
        rmdir($dir);
    }

    public function test_minimal_create_bundle(): void
    {
        $data = [
            'basePath' => '@basePath',
            'namespace' => 'App\Assets',
        ];

        $this->assertGreaterThan(
            0,
            $this
                ->creator
                ->setBasePath($data['basePath'])
                ->setNamespace($data['namespace'])
                ->create()
        );

        $this->assertAssetBundle(AssetBundleCreator::DEFAULT_CLASS, $data);
    }

    public function test_manifest_path_file_chunks(): void
    {
        $data = [
            'basePath' => '@basePath',
            'chunks' => ['runtime', 'vendor', 'main'],
            'manifest' => 'webpackManifest.json',
            'manifestPath' => '@manifestPath',
            'namespace' => 'App\Assets',
        ];

        $this->assertGreaterThan(
            0,
            $this
                ->creator
                ->setBasePath($data['basePath'])
                ->setChunks($data['chunks'])
                ->setManifest($data['manifest'])
                ->setManifestPath($data['manifestPath'])
                ->setNamespace($data['namespace'])
                ->create()
        );

        $this->assertAssetBundle(AssetBundleCreator::DEFAULT_CLASS, $data);
    }

    public function test_integer_options(): void
    {
        $data = [
            'basePath' => '@basePath',
            'namespace' => 'App\Assets',
            'cssPosition' => WebView::POSITION_HEAD,
            'jsPosition' => WebView::POSITION_HEAD,
        ];

        $this->assertGreaterThan(
            0,
            $this
                ->creator
                ->setBasePath($data['basePath'])
                ->setNamespace($data['namespace'])
                ->setCssPosition($data['cssPosition'])
                ->setJsPosition($data['jsPosition'])
                ->create()
        );

        $this->assertAssetBundle(AssetBundleCreator::DEFAULT_CLASS, $data);
    }

    public function test_manifest_exists(): void
    {
        $data = [
            'basePath' => '@basePath',
            'namespace' => 'App\Assets',
        ];

        $this
            ->creator
            ->setBasePath($data['basePath'])
            ->setNamespace($data['namespace'])
            ->create()
        ;

        sleep(1);

        $this->assertSame(
            0,
            $this
                ->creator
                ->create()
        );
    }

    private function assertAssetBundle(string $className, array $data)
    {
        $assetBundle = file_get_contents(
            self::$aliases
                ->get('@app')
            . DIRECTORY_SEPARATOR . 'Assets'
            . DIRECTORY_SEPARATOR . $className . '.php'
        );

        $this->assertSame($this->getExpected($data), $assetBundle);
    }

    private function getExpected(array $data): string
    {
        $vars = array_keys(self::DEFAULTS);
        $vars = array_splice($vars, 0, count(self::DEFAULTS) - 1);
        $data = array_merge(self::DEFAULTS, $data);

        foreach($data as $key => $value) {
            if (in_array($key, $vars)) {
                $data[$key] = self::var2String($value);
            }
        }

        $css = "['css/styles.6ea1680a204c02453cb633f581a29937.css']";

        if (isset($data['chunks'])) {
            $js = '['
                . "'js/runtime.30ef3250f37d7d37286c.js',"
                . "'js/vendor.cdbea9d9a3a046d4dee9.js',"
                . "'js/main.4ae995e9292c912d3ed9.js'"
                . ']'
            ;
        } else {
            $js = '['
                . "'js/manifest.30ef3250f37d7d37286c.js',"
                . "'js/vendor.cdbea9d9a3a046d4dee9.js',"
                . "'js/main.4ae995e9292c912d3ed9.js'"
                . ']'
            ;
        }

        return
<<<ASSET_BUNDLE
<?php
/**
 * Do not edit this file; it is automatically created 
 */

declare(strict_types=1);

namespace {$data['namespace']};

use Yiisoft\Assets\AssetBundle;

final class {$data['className']} extends AssetBundle
{
    public ?string \$basePath = {$data['basePath']};
    public ?string \$baseUrl = {$data['baseUrl']};
    public array \$css = $css;
    public array \$cssOptions = {$data['cssOptions']};
    public ?int \$cssPosition = {$data['cssPosition']};
    public array \$js = $js;
    public array \$jsOptions = {$data['jsOptions']};
    public ?int \$jsPosition = {$data['jsPosition']};
}
ASSET_BUNDLE;
    }

    private static function var2String(mixed $var): string
    {
        return match (gettype($var)) {
            'array' => empty($var) ? '[]' : "['" . implode("','", $var) . "']",
            'integer' => (string)$var,
            'NULL' => 'null',
            'string' => "'" . $var . "'",
        };
    }
}


