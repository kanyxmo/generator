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

use Composer\InstalledVersions;
use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Schema\Schema;
use Hyperf\Support\Filesystem\Filesystem;
use Mine\Application;
use Mine\ComposerManager;
use Mine\Devtool\Visitor\GenOption;
use Mine\Devtool\Visitor\GenVisitor;
use Mine\Devtool\Visitor\RemoveNoUnusedImport;
use Hyperf\Stringable\Str;
use Mine\Utils\Utils;
use PhpParser\BuilderFactory;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\{Error, Lexer, Lexer\Emulative, NodeTraverser, Parser, ParserFactory, PrettyPrinterAbstract, PrettyPrinter\Standard};
use RuntimeException;
use Throwable;

use function Hyperf\Support\env;

abstract class AbstractGenerator
{
    protected Application $app;

    protected Filesystem $fs;

    protected ConfigInterface $config;

    protected ConnectionResolverInterface $resolver;

    protected BuilderFactory $factory;

    protected Lexer $lexer;

    protected Parser $parser;

    protected Standard $printer;

    protected GenOption $option;

    protected bool $noPrefix = false;

    public function __construct(
        protected string $current_app,
        protected string $name,
        protected ?string $before = null,
        protected ?string $after = null,
        protected ?string $suffix = null,
        protected ?string $prefix = null,
        protected bool $is_suffix = true,
        protected ?string $inheritance = null,
        protected ?string $implements = null,
    ) {
        $this->app = container()
            ->get(Application::class);
        $this->config = container()
            ->get(ConfigInterface::class);
        $this->fs = container()
            ->get(Filesystem::class);
        $this->resolver = container()
            ->get(ConnectionResolverInterface::class);
        $this->factory = new BuilderFactory();
        $this->lexer = new Lexer([
            'usedAttributes' => ['comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos'],
        ]);
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7, $this->lexer);
        $this->printer = new Standard();
        $this->initialize();
        if ($this->fs->isDirectory($this->app->getAppInfo($this->current_app, 'fullPath', null) . '/Appliction')) {
            $this->fs->deleteDirectory($this->app->getAppInfo($this->current_app, 'fullPath', null) . '/Appliction');
        }
    }

    public function __set($name, mixed $value)
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }
        $this->initialize();

        return $this;
    }

    public function generate(): string
    {
        $this->configure();

        try {
            $this->before();
            $this->write($this->option->getFile(), $this->code());
            $this->after();
            (new CodeFixer())->fix($this->option->getFile());

            return $this->option->getClass();
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function getFile(): string
    {
        return $this->option->getFile();
    }

    public function preview(): array
    {
        $this->configure();

        return [$this->option->getClass(), $this->code(), $this->option->getNamespace(), $this->option->getFile()];
    }

    public function qualifyClass(): string
    {
        $class = Str::studly($this->name);
        $namespace = $this->getNamespace();
        // if (!Str::startsWith($namespace, $class)) {
        $class = $namespace . '\\' . $class;
        // }

        $config = $this->getConfig();
        if ($this->is_suffix) {
            $class .= Str::studly(trim((string) $config['suffix']));
        }

        return Str::replace('\\\\', '\\', $class);
    }

    public function getIdentifier(?string $code = null): ?string
    {
        // prefix
        $identifier = $this->noPrefix ? '' : $this->prefix;

        if (! $identifier) {
            $identifier = Str::replace(['-'], '_', Str::after($this->getApp('name'), '/'));
        }

        if ($identifier == 'no') {
            $identifier = '';
        }

        if ($code) {
            $code = Str::snake(Str::replace($identifier, '', Str::snake($code)));
            if (Str::beforeLast(Str::after($code, '_'), '_') == Str::afterLast(Str::after($code, '_'), '_')) {
                $code = Str::before($code, '_') . ':' . Str::after($code, '_');
            } else {
                $code = Str::before($code, '_') . ':' . Str::beforeLast(
                    Str::after($code, '_'),
                    '_'
                ) . ':' . Str::afterLast(Str::after($code, '_'), '_');
            }

            $identifier .= sprintf(':%s', $code);
        }

        if (Str::startsWith($identifier, ':')) {
            $identifier = Str::after($identifier, ':');
        }

        return Str::snake(Str::replace('::', ':', $identifier));
    }

    public function getApp(?string $key = null)
    {
        if (! InstalledVersions::isInstalled($this->current_app)) {
            throw new RuntimeException(sprintf('应用%s未安装或非开发模式!', $this->current_app));
        }

        $info = $this->app->getAppInfo($this->current_app, $key, null);
        if ($key && $info && $key == 'fullPath') {
            $path = "{$info}/";
            if ($this->before) {
                $path .= Str::studly(trim((string) $this->before)) . '/';
            }
            $path .= Str::replaceLast('Gen', '', Str::afterLast(static::class, '\\')) . '/';
            if ($this->after) {
                $path .= Str::studly(trim((string) $this->after)) . '/';
            }

            return Str::replace(['\\', '\\\\', '//'], '/', $path);
        }

        return $info;
    }

    public function getTitle(?string $name = null): string
    {
        $commit = $this->getConnection()
            ->select('SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES  WHERE  TABLE_NAME = ?', [
                env('DB_PREFIX') . Str::snake($name ?: $this->name),
            ]);
        if ($commit) {
            $commit = (array) $commit[0];
            $commit = Str::beforeLast(Str::replace('列表', '管理', reset($commit)), '表');
        } else {
            $commit = '请命名';
        }

        return $commit;
    }

    // protected function formatColumns(string $class=null): array
    // {
    //     $schema = $this->getConnection()->getSchemaBuilder();
    //     assert($schema instanceof Schema);
    //     $columns = $schema->getColumnTypeListing(Str::snake($this->name));
    //     return array_map(static fn ($item) => array_change_key_case($item, CASE_LOWER), $columns);
    // }
    // protected function getColumns($class, $columns, $forceCasts): array
    // {
    //     /** @var Model */
    //     $model = new $class();
    //     $dates = $model->getDates();
    //     $casts = [];
    //     if (! $forceCasts) {
    //         $casts = $model->getCasts();
    //     }

    //     foreach ($dates as $date) {
    //         if (! isset($casts[$date])) {
    //             $casts[$date] = 'datetime';
    //         }
    //     }

    //     // show create table about
    //     foreach ($columns as $key => $value) {
    //         $columns[$key]['cast'] = $casts[$value['column_name']] ?? null;
    //     }

    //     return $columns;
    // }
    protected function getPrimaryKey(array $columns): string
    {
        $primaryKey = 'id';
        foreach ($columns as $column) {
            if ($column['column_key'] === 'PRI') {
                $primaryKey = $column['column_name'];

                break;
            }
        }

        return $primaryKey;
    }

    protected function code(): string
    {
        try {
            $code = null;
            if (! $this->fs->exists($this->option->getFile())) {
                $node = $this->factory->namespace($this->option->getNamespace());
                foreach ($this->option->getUses() as $use => $alias) {
                    if ($alias) {
                        $node->addStmt($this->factory->use($use)->as($alias));
                    } else {
                        $node->addStmt($this->factory->use($use));
                    }
                }

                $class = match ($this->option->getType()) {
                    $this->option::TYPE_CLASS => $this->factory->class($this->getClassName($this->option->getClass())),
                    $this->option::TYPE_INTERFACE => $this->factory->interface(
                        $this->getClassName($this->option->getClass())
                    ),
                    $this->option::TYPE_TRAIT => $this->factory->trait($this->getClassName($this->option->getClass())),
                    $this->option::TYPE_ENUM => $this->factory->enum($this->getClassName($this->option->getClass())),
                    default => $this->factory->class($this->getClassName($this->option->getClass())),
                };
                foreach ($this->option->getMethods() as $method) {
                    $class->addStmt($method);
                }
                if ($this->option->getAttributes()) {
                    foreach ($this->option->getAttributes() as $attribute) {
                        $class->addAttribute($attribute);
                    }
                }
                foreach ($this->option->getPropertys() as $property) {
                    $class->addStmt($property);
                }
                $stmts = [$node->addStmt($class)->getNode()];
                $code = $this->printer->prettyPrintFile($stmts);
            }
            $originStmts = $this->parser->parse($code ?: $this->fs->get($this->option->getFile()));
            foreach ($this->option->getVisitors() as $visitor) {
                $traverser = new NodeTraverser();
                $traverser->addVisitor(new NodeConnectingVisitor());
                $traverser->addVisitor(is_object($visitor) ? $visitor : new $visitor($this->option));
                $originStmts = $traverser->traverse($originStmts);
            }
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NodeConnectingVisitor());
            $traverser->addVisitor(new RemoveNoUnusedImport($this->option));
            $stmts = $traverser->traverse($originStmts);
            $originTokens = $this->lexer->getTokens();

            return $this->printer->printFormatPreserving($stmts, $originStmts, $originTokens);
        } catch (Throwable $throwable) {
            throw $throwable;
        }
    }

    protected function initialize(): void
    {
        try {
            // 初始化构建配置
            if ($this->before == '') {
                $this->before == null;
            }
            $this->option = new GenOption($this->getNamespace(), $this->qualifyClass(), $this->getPath(
                $this->qualifyClass()
            ));
            $this->option->setVisitors(GenVisitor::class, 99);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function before(): void
    {
    }

    protected function after(): void
    {
    }

    protected function getNamespace(): string
    {
        $namespace = $this->getApp('namespace');
        $config = $this->getConfig();
        if ($this->before) {
            $namespace .= '\\' . Str::studly(trim((string) $this->before));
        }
        // if ($this->channel) {
        //     $namespace .= '\\' . Str::studly(trim((string) $this->channel));
        // }
        $namespace .= '\\' . Str::studly(trim((string) $config['namespace']));
        if ($this->after) {
            $namespace .= '\\' . Str::studly(trim((string) $this->after));
        }
        $namespace = Str::replace('\\\\', '\\', $namespace);
        if (Str::endsWith($namespace, '\\')) {
            Str::beforeLast($namespace, '\\');
        }

        return $namespace;
    }

    protected function getConnection(): ConnectionInterface|Connection
    {
        return $this->resolver->connection();
    }

    protected function getClassName(string $class): string
    {
        return Utils::getClassBaseName($class);
    }

    protected function getPath(string $name, ?string $extension = '.php'): string
    {
        $name = $this->getClassName($name);

        return Str::replace('//', '/', sprintf('%s/%s', $this->getApp('fullPath'), sprintf('%s%s', $name, $extension)));
    }

    protected function write(string $file, string $code): string
    {
        if (! $this->fs->exists(dirname($file))) {
            $this->fs->makeDirectory(dirname($file), 0755, true);
        }

        $this->fs->put($file, $code);
        if (! class_exists($this->option->getClass())) {
            ComposerManager::autoloadClass();
        }

        return $file;
    }

    protected function getConfig(): array
    {
        $namespace = Str::replaceLast('Gen', '', Str::afterLast(static::class, '\\'));
        $key = 'devtool.generator.' . Str::snake($this->after ?: $namespace, '.');
        $config = $this->config->get($key, [
            'namespace' => Str::studly(trim((string) $namespace)),
            'inheritance' => null,
            'implements' => null,
            'suffix' => Str::studly(trim((string) $namespace)),
        ]);
        if (! isset($config['namespace'])) {
            $config['namespace'] = Str::studly(trim((string) $namespace));
        }
        if (! isset($config['inheritance'])) {
            $config['inheritance'] = null;
        }

        if (! isset($config['implements'])) {
            $config['implements'] = null;
        }

        if (! isset($config['suffix'])) {
            $config['suffix'] = Str::studly(trim((string) $namespace));
        }

        return $config;
    }

    abstract protected function configure(): void;
}
