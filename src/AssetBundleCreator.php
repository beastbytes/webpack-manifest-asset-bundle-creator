<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Webpack\Manifest;

use InvalidArgumentException;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Json\Json;

use const DIRECTORY_SEPARATOR;

/**
 * Creates an asset bundle to register the chunks defined in the Webpack manifest created by the
 * {@link WebpackManifestPlugin https://github.com/shellscape/webpack-manifest-plugin}
 */
class AssetBundleCreator
{
    public const DEFAULT_BASE_URL = '/';
    public const DEFAULT_CHUNKS = ['manifest', 'vendor', 'main'];
    public const DEFAULT_CLASS = 'WebpackManifest';
    public const DEFAULT_MANIFEST = 'manifest.json';

    /** @var string File extension for CSS files */
    private const TYPE_CSS = 'css';
    /** @var string File extension for JavaScript files */
    private const TYPE_JS = 'js';

    private ?string $basePath = null;
    private string $baseUrl = self::DEFAULT_BASE_URL;
    private array $chunks = self::DEFAULT_CHUNKS;
    private string $className = self::DEFAULT_CLASS;
    private array $css = [];
    private array $cssOptions = [];
    private ?int $cssPosition = null;
    private array $js = [];
    private array $jsOptions = [];
    private ?int $jsPosition = null;
    private string $manifest = self::DEFAULT_MANIFEST;
    private ?string $manifestPath = null;
    private string $namespace = '';

    public function __construct(private Aliases $aliases)
    {
    }

    /**
     * @return bool|int The number of bytes written or false on failure.
     * @throws \JsonException
     */
    public function create(): bool|int
    {
        $assetBundleDir = str_replace(
            '\\',
            '/',
            $this->
                aliases
                ->get('@' . lcfirst(str_replace('\\', DIRECTORY_SEPARATOR, $this->namespace)))
        );

        $assetBundleFile = $assetBundleDir . DIRECTORY_SEPARATOR . $this->className . '.php';

        $manifestFile = $this
            ->aliases
            ->get($this->manifestPath ?? $this->basePath) . DIRECTORY_SEPARATOR . $this->manifest
        ;

        if (file_exists($assetBundleFile) && filemtime($assetBundleFile) > filemtime($manifestFile)) {
            return 0;
        }

        if (
            !is_dir($assetBundleDir)
            && !mkdir($assetBundleDir, 0766, true)
            && !is_dir($assetBundleDir)
        ) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $assetBundleDir));
        }

        /** @psalm-var array<string, string> $manifest */
        $manifest = Json::decode(file_get_contents($manifestFile));

        /** @var string $chunk */
        foreach ($this->chunks as $chunk) {
            foreach ([self::TYPE_CSS, self::TYPE_JS] as $fileType) {
                $chunkName = "$chunk.$fileType";
                if (array_key_exists($chunkName, $manifest)) {
                    $this->$fileType[] = $manifest[$chunkName];
                }
            }
        }

        $stream = fopen($assetBundleFile, 'wb');

        if ($stream === false) {
            return false;
        }

        $result = fwrite($stream, $this->getAssetBundle());
        return fclose($stream) ? $result : false;
    }

    /**
     * The Web-accessible directory that contains the asset files in the bundle.
     *
     * Can be a directory or an alias of the directory.
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * The base URL for the relative asset files listed in {@see $js} and {@see $css}.
     *
     * Can be a URL or an alias of the URL.
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Names of the chunks in the dependency order starting with the manifest through to the entry point
     */
    public function setChunks(array $chunks): self
    {
        $this->chunks = $chunks;
        return $this;
    }

    /**
     * Classname of the asset bundle
     */
    public function setClassName(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    /**
     * The options that will be passed to {@see \Yiisoft\View\WebView::registerCssFile()}
     * when registering the CSS files in this bundle.
     */
    public function setCssOptions(array $cssOptions): self
    {
        $this->cssOptions = $cssOptions;
        return $this;
    }

    /**
     * Specifies where the `<style>` tag should be inserted in a page.
     *
     * When this package is used with [`yiisoft/view`](https://github.com/yiisoft/view), the possible values are:
     *  - {@see \Yiisoft\View\WebView::POSITION_HEAD} - in the head section. This is the default value.
     *  - {@see \Yiisoft\View\WebView::POSITION_BEGIN} - at the beginning of the body section.
     *  - {@see \Yiisoft\View\WebView::POSITION_END} - at the end of the body section.
     */
    public function setCssPosition(?int $cssPosition): self
    {
        $this->cssPosition = $cssPosition;
        return $this;
    }

    /**
     * The options that will be passed to {@see \Yiisoft\View\WebView::registerJsFile()}
     * when registering the JS files in this bundle.
     */
    public function setJsOptions(array $jsOptions): self
    {
        $this->jsOptions = $jsOptions;
        return $this;
    }

    /**
     * Specifies where the `<script>` tag should be inserted in a page.
     *
     * When this package is used with [`yiisoft/view`](https://github.com/yiisoft/view), the possible values are:     *
     *  - {@see \Yiisoft\View\WebView::POSITION_HEAD} - in the head section. This is the default value
     *    for JavaScript variables.
     *  - {@see \Yiisoft\View\WebView::POSITION_BEGIN} - at the beginning of the body section.
     *  - {@see \Yiisoft\View\WebView::POSITION_END} - at the end of the body section. This is the default value
     *    for JavaScript files and blocks.
     *  - {@see \Yiisoft\View\WebView::POSITION_READY} - at the end of the body section (only for JavaScript strings and
     *    variables). This means the JavaScript code block will be executed when HTML document composition is ready.
     *  - {@see \Yiisoft\View\WebView::POSITION_LOAD} - at the end of the body section (only for JavaScript strings and
     *    variables). This means the JavaScript code block will be executed when HTML page is completely loaded.
     */
    public function setJsPosition(?int $jsPosition): self
    {
        $this->jsPosition = $jsPosition;
        return $this;
    }

    /**
     * Name of the manifest file.
     */
    public function setManifest(string $manifest): self
    {
        $this->manifest = $manifest;
        return $this;
    }

    /**
     * Path to the manifest file.
     *
     *  Can be a directory or an alias of the directory.
     */
    public function setManifestPath(?string $manifestPath): self
    {
        $this->manifestPath = $manifestPath;
        return $this;
    }

    /**
     * Namespace of the asset bundle
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    private function getAssetBundle(): string
    {
        return
<<<ASSET_BUNDLE
<?php
/**
 * Do not edit this file; it is automatically created 
 */

declare(strict_types=1);

namespace {$this->namespace};

use Yiisoft\Assets\AssetBundle;

final class {$this->className} extends AssetBundle
{
    public ?string \$basePath = {$this->var2String($this->basePath)};
    public ?string \$baseUrl = {$this->var2String($this->baseUrl)};
    public array \$css = {$this->var2String($this->css)};
    public array \$cssOptions = {$this->var2String($this->cssOptions)};
    public ?int \$cssPosition = {$this->var2String($this->cssPosition)};
    public array \$js = {$this->var2String($this->js)};
    public array \$jsOptions = {$this->var2String($this->jsOptions)};
    public ?int \$jsPosition = {$this->var2String($this->jsPosition)};
}
ASSET_BUNDLE;
    }

    private function var2String(mixed $var): string
    {
        return match (gettype($var)) {
            'array' => empty($var) ? '[]' : "['" . implode("','", $var) . "']",
            'integer' => (string)$var,
            'NULL' => 'null',
            'string' => "'" . $var . "'",
        };
    }
}
