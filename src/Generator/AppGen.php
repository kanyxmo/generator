<?php

declare(strict_types=1);

/**
 * #logic 做事不讲究逻辑，再努力也只是重复犯错
 * ## 何为相思：不删不聊不打扰，可否具体点：曾爱过。何为遗憾：你来我往皆过客，可否具体点：再无你。
 * ## 摔倒一次可以怪路不平鞋不正，在同一个地方摔倒两次，只能怪自己和自己和解，无不是一个不错的选择。
 * @version 1.0.0
 * @author @小小只^v^ <littlezov@qq.com>  littlezov@qq.com
 * @link     https://github.com/littlezo
 * @document https://github.com/littlezo/wiki
 * @license  https://github.com/littlezo/MozillaPublicLicense/blob/main/LICENSE
 *
 */

namespace Mine\Devtool\Generator;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Stringable\Str;
use Hyperf\Support\Filesystem\Filesystem;
use Mine\Application;
use Mine\ConfigRegister;
use Mine\Vo\AppInfoVo;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};
use RuntimeException;

class AppGen
{
    #[Inject()]
    protected Application $app;

    protected Filesystem $fs;

    protected array $generator = [];

    protected array $package = [];

    protected array $devPackage = [];

    protected array $license = [];

    /**
     * 创建应用.
     *
     * @property array $info
     * @property string $info.name
     * @property string $info.author
     * @property string $info.title
     * @property string $info.description
     * @property string $info.version
     */
    public function __construct(
        protected array $info,
        ?string $path = null
    ) {
        if (!isset($this->info['name']) || !$this->info['name']) {
            throw new RuntimeException('请输入应用名');
        }

        if (!isset($this->info['author']) || !$this->info['author']) {
            throw new RuntimeException('请输入作者');
        }

        if (!isset($this->info['version']) || !$this->info['version']) {
            throw new RuntimeException('请输入版本');
        }
        if($path){
            $path .= '/';
        }
        $path .= ($this->info['author'] == 'little') ? sprintf('%s/%s/src', $this->info['author']) : $this->info['author'];
        // dump($path);exit;
        $config = container()->get(ConfigInterface::class);
        assert($config instanceof ConfigInterface);
        $this->app->setAppPath($path);
        $this->generator = $config->get('devtool.generator', []);
        $this->package =  $config->get('mine.package', []);
        $this->license =  $config->get('mine.license', []);
        $this->devPackage =  $config->get('mine.package-dev', []);
        $this->fs = container()->get(Filesystem::class);
    }

    public function create()
    {
        try {
            return $this->package();
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function upgrade(): bool
    {
        try {
            $apps = $this->app->getAppInfo();
            $v = $this->info['version'];
            $vv = Str::replace('.', '', $v);
            foreach ($apps as $key => $app) {
                if ($app instanceof AppInfoVo) {
                    $app = $app->toArray();
                }
                [$author, $name] = explode('/', (string) $app['name']);
                $vvv = Str::replace('.', '', $app['version']);
                $info = [
                    'name' => $name,
                    'author' => $author,
                    'title' => $app['title'],
                    'description' => $app['description'],
                    'version' => ($vv <= $vvv) ? $app['version'] : $v,
                    'icon' => $app['icon'],
                ];
                $path = Str::replaceLast($name, '', $app['path']);
                $this->app->setAppPath($path);
                $this->info = $info;
                $this->package();
            }

            return true;
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function package(): bool
    {
        try {
            $app = $this->info;
            $package = Str::kebab($app['author']) . '/' . Str::kebab($app['name']);
            $namespace = Str::studly($app['author']) . '\\' . Str::studly($app['name']);
            $testNamespace = Str::studly($app['author']) . 'Test\\' . Str::studly($app['name']);
            $path = $this->app->getAppPath($app['name']);
            // dump($path);exit;
            $jsonFile = (new JsonFile(sprintf('%s/composer.json', $path)));
            if (!$jsonFile->exists()) {
                (!$this->fs->exists(sprintf('%s/tests', $path))) && $this->fs->makeDirectory(
                    sprintf('%s/tests', $path),
                    0755,
                    true
                );
            }

            // $this->makeDirs(sprintf('%s/src', $path), $this->generator);
            $composer = new JsonConfigSource($jsonFile);
            $composer->addProperty('name', $package);
            $composer->addProperty('title', $app['title'] ?? '');
            $composer->addProperty('description', $app['description'] ?? '');
            $composer->addProperty('license', $app['license'] ?? 'MPL-2.0');
            $composer->addProperty('type', $app['type'] ?? 'application');
            $composer->addProperty('version', $app['version'] ?? '1.0.0');
            $composer->addProperty('pretty_version', $app['version'] ?? '');
            $composer->addProperty('app_type', $app['app_type'] ?? 'service');
            $composer->addProperty('auth', $app['auth'] ?? true);
            $composer->addProperty('permissions', $app['permissions'] ?? ['super']);
            $composer->addProperty('icon', $app['icon'] ?? isset($app['icon']) ? $app['icon'] : 'ic:baseline-security');
            $composer->addProperty('keywords', [$app['name'], $app['author']]);
            $composer->addProperty('homepage', 'https://store.yczx.vip');
            $composer->addProperty('support', [
                'docs' => 'https://docs.yczx.vip',
                'issues' => 'https://github.com/littlezo/littler/issues',
                'source' => 'https://github.com/littlezo/littler',
            ]);
            $composer->addProperty('authors', [[
                'name' => '@小小只^v^',
                'email' => 'littlezov@qq.com',
            ]]);
            foreach ($this->package as $pkg => $ver) {
                $composer->addLink('require', $pkg, $ver);
            }

            $composer->addProperty('autoload', [
                'psr-4' => [
                    sprintf('%s\\', $namespace) => 'src/',
                ],
            ]);
            $composer->addProperty('autoload-dev', [
                'psr-4' => [
                    sprintf('%s\\', $testNamespace) => 'src/',
                ],
            ]);
            $composer->addConfigSetting('sort-packages', true);
            $composer->addConfigSetting('optimize-autoloader', true);
            $composer->addProperty('extra.branch-alias.dev-main', $app['version']);
            $composer->addProperty('extra.hyperf.config', sprintf('%s\ConfigProvider', $namespace));
            $jsonFile->validateSchema(JsonFile::AUTH_SCHEMA);

            $jsonFile->write($jsonFile->read());
            $gitignore = <<<'CODE'
                .vscode
                .buildpath
                .settings
                .project
                *.patch
                .idea
                .git
                vendor
                runtime
                .phpintel
                .DS_Store
                .phpunit*
                *.lock
                *.cache
                CODE;
            $provider = $this->provider($namespace);
            if(!$this->fs->exists(sprintf('%s/src', $path))) $this->fs->makeDirectory(sprintf('%s/src', $path));
            $this->fs->put(sprintf('%s/.gitignore', $path), $gitignore);
            $this->fs->put(sprintf('%s/src/ConfigProvider.php', $path), $provider);
            foreach ($this->license as $name => $license) {
                if (file_exists($license)) {
                    $this->fs->put(sprintf('%s/%s', $path, $name), file_get_contents($license));
                }
            }

            return true;
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function makeDirs($path, $dirs = []): void
    {
        try {
            foreach ($dirs as $key => $gen) {
                if (isset($gen['namespace']) && $dir = $gen['namespace']) {
                    if (is_array($dir)) {
                        $this->makeDirs(sprintf('%s/', $path) . Str::studly($key), $dir);
                    } else {
                        $dir = sprintf('%s/', $path) . ($dir == 'config' ? $dir : Str::studly($dir));

                        (!$this->fs->exists($dir)) && $this->fs->makeDirectory($dir, 0755, true);
                    }
                }
            }
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function config(): array
    {
        return $this->info;
    }

    protected function provider(string $namespace)
    {
        try {
            $factory = new BuilderFactory();
            $node = $factory->namespace($namespace)
                ->addStmt($factory->use(ConfigRegister::class))
                ->addStmt(
                    $factory->class('ConfigProvider')
                        ->addStmt(
                            $factory->method('__invoke')
                                ->makePublic()
                                ->setReturnType('array')
                                ->addStmt(
                                    new Return_(
                                        $factory->staticCall(
                                            'ConfigRegister',
                                            'register',
                                            $factory->args([new Dir(), [], new ConstFetch(new Name('true'))]),
                                        )
                                    )
                                )
                        )
                )->getNode();
            $printer = new Standard();

            return $printer->prettyPrintFile([$node]);
        } catch (Exception $exception) {
            throw $exception;
        }
    }
}
