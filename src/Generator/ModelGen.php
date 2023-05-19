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

use Exception;
use Hyperf\Contract\CastsAttributes;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Commands\Ast\ModelRewriteConnectionVisitor;
use Hyperf\Database\Commands\ModelData;
use Hyperf\Database\Commands\ModelOption;
use Hyperf\Database\Connection;
use Hyperf\Database\Schema\Builder;
use Hyperf\Database\Schema\Schema;
use Mine\ComposerManager;
use Mine\Devtool\Visitor\ModelVisitor;
use Mine\Devtool\Visitor\RemoveNoUnusedImport;
use Mine\Model\JsonAttributes;
use Mine\Model\Model;
use Hyperf\Stringable\Str;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

use function Hyperf\Support\env;
use function Hyperf\Support\make;

class ModelGen extends AbstractGenerator
{
    protected string $table;

    public function __construct(
        string $current_app,
        string $name,
        ?string $before = null,
        ?string $after = null,
        ?string $suffix = null,
        ?string $prefix = null,
        bool $is_suffix = true,
        ?string $inheritance = null,
        ?string $implements = null,
        protected ?ModelOption $modelOption = null,
        protected string $pool = 'default'
    ) {
        parent::__construct($current_app, $name, $before, $after, $suffix, $prefix, $is_suffix, $inheritance, $implements);

        $this->config = container()
            ->get(ConfigInterface::class);
        if (! $modelOption instanceof ModelOption) {
            $option = new ModelOption();
            $option->setPool($this->pool)
                ->setPath(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'path'), $this->getApp('fullPath'))
                )
                ->setPrefix($this->config->get(sprintf('databases.%s.%s', $this->pool, 'prefix'), env('DB_PREFIX', '')))
                ->setInheritance(
                    $this->config->get('inheritance', 'commands.gen:model.inheritance', $this->pool, 'Model')
                )
                ->setUses(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.uses'), Model::class)
                )
                ->setForceCasts(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.force_casts'), true)
                )
                ->setRefreshFillable(
                    $this->config->get(sprintf(
                        'databases.%s.%s',
                        $this->pool,
                        'commands.gen:model.refresh_fillable'
                    ), true)
                )
                ->setTableMapping(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.table_mapping'), [])
                )
                ->setIgnoreTables(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.ignore_tables'), [])
                )
                ->setWithComments(
                    $this->config->get(sprintf(
                        'databases.%s.%s',
                        $this->pool,
                        'commands.gen:model.with_comments'
                    ), true)
                )
                ->setWithIde(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.with_ide'), false)
                )
                ->setVisitors(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.visitors'), [])
                )
                ->setPropertyCase(
                    $this->config->get(sprintf('databases.%s.%s', $this->pool, 'commands.gen:model.property_case'))
                );
            $this->modelOption = $option;
        }
        $this->name = Str::replaceFirst($this->modelOption->getPrefix(), '', Str::snake($name));
    }

    public function getColumns(?array $columns = []): array
    {
        $class = $this->option->getClass();
        /** @var Model $model */
        $model = new $class();
        $dates = $model->getDates();
        $casts = [];
        if (! $this->modelOption->isForceCasts()) {
            $casts = $model->getCasts();
        }

        foreach ($dates as $date) {
            if (! isset($casts[$date])) {
                $casts[$date] = 'datetime';
            }
        }
        if (! count((array) $columns)) {
            $columns = $this->formatColumns();
        }
        foreach ($columns as $key => $value) {
            $columns[$key]['cast'] = $casts[$value['column_name']] ?? null;
            if ($value['data_type'] == 'json') {
                $metaGen = (new AttributesGen(
                    $this->current_app,
                    "{$this->name}_{$value['column_name']}",
                    'Model',
                    is_suffix: false
                ));
                $columns[$key]['cast'] = $metaGen->qualifyClass();
                // dump( $metaGen->preview());
                // $voGen = (new ViewObjectGen($this->current_app, sprintf('%s_%s', $this->name, $item['column_name'])));
                // $voGen->commit = $item['column_comment'];
                // $vo = $voGen->generate();
                // $item['vo'] = $vo;
                // $uses[$vo] = null;
                // $vo = $voGen->preview();
                $columns[$key]['vo'] = null;
            } else {
                $columns[$key]['vo'] = null;
            }
        }

        return $columns;
    }

     protected function configure(): void
     {
     }

    protected function getSchemaBuilder(?string $poolName = null): Builder|Schema
    {
        /** @var Connection $connection */
        $connection = $this->resolver->connection($this->modelOption->getPool());

        return $connection->getSchemaBuilder();
    }

    protected function code(): string
    {
        try {
            $columns = $this->formatColumns();
            $class = $this->getClass();
            $code = null;
            if (! file_exists($this->option->getFile())) {
                $factory = new BuilderFactory();
                $node = $factory->namespace($this->getNamespace())
                    ->addStmt($factory->use($this->modelOption->getUses()))
                    ->addStmt(
                        $factory->class($this->getClassName($class))
                            ->extend($this->modelOption->getInheritance())
                            ->addStmt(
                                $factory->property('table')
                                    ->makeProtected()
                                    ->setDefault($this->name)
                                    ->setType('?string')
                                    ->setDocComment('/**
                                    * 数据表名称
                                    */')
                            )->addStmt(
                                $factory->property('connection')
                                    ->makeProtected()
                                    ->setDefault($this->modelOption->getPool())
                                    ->setType('?string')
                                    ->setDocComment('/**
                                    * 数据库连接
                                    */')
                            )->addStmt(
                                $factory->property('fillable')
                                    ->makeProtected()
                                    ->setDefault([])
                                    ->setType('array')
                                    ->setDocComment('/**
                                    * 允许被批量赋值的属性
                                    */')
                            )->addStmt(
                                $factory->property('casts')
                                    ->makeProtected()
                                    ->setDefault([])
                                    ->setType('array')
                                    ->setDocComment('/**
                                    * 数据格式化配置
                                    */')
                            )
                    )
                    ->getNode();

                $stmts = [$node];
                $code = $this->printer->prettyPrintFile($stmts);
                $this->write($this->option->getFile(), $code);
                if (! class_exists($this->option->getClass())) {
                    ComposerManager::autoloadClass();
                }
            }

            $columns = $this->getColumns($columns);
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NodeConnectingVisitor());
            $uses = [
                $this->modelOption->getUses() => $this->modelOption->getInheritance(),
            ];
            foreach ($columns as &$item) {
                if ($item['data_type'] == 'json') {
                    $metaGen = (new AttributesGen(
                        $this->current_app,
                        "{$this->name}_{$item['column_name']}",
                        'Model',
                        is_suffix: false
                    ));
                    $metaGen->implements = CastsAttributes::class;
                    $metaGen->inheritance = JsonAttributes::class;
                    $item['cast'] = $metaGen->generate();
                    // dump( $metaGen->preview());
                    // $voGen = (new ViewObjectGen($this->current_app, sprintf('%s_%s', $this->name, $item['column_name'])));
                    // $voGen->commit = $item['column_comment'];
                    // $vo = $voGen->generate();
                    // $item['vo'] = $vo;
                    // $uses[$vo] = null;
                    // $vo = $voGen->preview();
                    $item['vo'] = null;
                } else {
                    $item['vo'] = null;
                }
            }
            $attributesGen = (new AttributesGen(
                $this->current_app,
                "{$this->name}Item",
                'Model',
                is_suffix: false
            ));
            $attributesGen->properties = $columns;
            $attributesGen->generate();
            $traverser->addVisitor(make(ModelVisitor::class, [
                'class' => $class,
                'columns' => $columns,
                'option' => $this->modelOption,
                'uses' => $uses,
                'exclude' => ['created_id', 'updated_id', 'deleted_id', 'created_time', 'updated_time', 'deleted_time'],
            ]));
            $traverser->addVisitor(make(ModelRewriteConnectionVisitor::class, [$class, $this->modelOption->getPool()]));
            $data = make(ModelData::class, [
                'class' => $class,
                'columns' => $columns,
            ]);
            foreach ($this->modelOption->getVisitors() as $visitorClass) {
                $traverser->addVisitor(make($visitorClass, [$this->modelOption, $data]));
            }
            $traverser->addVisitor(new class() extends NodeVisitorAbstract {
                public function leaveNode(Node $node)
                {
                    if ($node instanceof ClassMethod) {
                        return NodeTraverser::REMOVE_NODE;
                    }

                    return $node;
                }
            });

            $originStmts = $this->parser->parse($code ?: $this->fs->get($this->option->getFile()));
            $originTokens = $this->lexer->getTokens();
            $newStmts = $traverser->traverse($originStmts);
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new ParentConnectingVisitor());
            $traverser->addVisitor(new RemoveNoUnusedImport($this->option));
            $newStmts = $traverser->traverse($newStmts);

            $code = $this->printer->prettyPrintFile($newStmts);
            // $this->fs->put($path, $code);
            // $this->io->info(sprintf('Model %s 构建成功', $class));
            return $code;
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function after(): void
    {
        try {
            (new MapperGen($this->current_app, $this->name))->generate();
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function getClass(): string
    {
        return $this->modelOption->getTableMapping()[$this->name] ?? $this->qualifyClass();
    }

    protected function formatColumns(): array
    {
        $columns = $this->getSchemaBuilder()
            ->getColumnTypeListing(Str::snake($this->name));

        return array_map(static fn ($item): array => array_change_key_case($item, CASE_LOWER), $columns);
    }
}
