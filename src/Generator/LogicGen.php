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

use Closure;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Mine\Abstract\AbstractLogic;
use Mine\Devtool\Visitor\AbstractVisitor;
use Mine\Devtool\Visitor\GenOption;
use Mine\Request\BaseSort;
use Mine\Request\Page;
use Mine\Response\SuccessResponse;
use PhpParser\Node\Attribute;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

class LogicGen extends AbstractGenerator
{
    protected ?array $relation = [];

    protected function configure(): void
    {
        // if ($this->fs->exists($this->option->getFile())) {
        //     $this->fs->delete($this->option->getFile());
        // }
        $this->implements = (new ContractGen($this->current_app, $this->name))->qualifyClass();
        $this->option->setUse(AbstractLogic::class)
            ->setInheritance(AbstractLogic::class)
            ->setUse(SuccessResponse::class)
            ->setUse(BaseSort::class)
            ->setUse(Page::class)
            ->setUse(Closure::class);
        if ($this->implements && (class_exists($this->implements) || interface_exists($this->implements))) {
            $this->option->setUse($this->implements)
                ->setProperty(
                    'instance',
                    $this->factory->property('instance')
                        ->makeProtected()
                        ->addAttribute(new Attribute(new Name($this->getClassName(Inject::class))))
                        ->setType($this->getClassName($this->implements))
                        ->getNode()
                );
        } else {
            $this->option->removeUse($this->implements);
        }
        $this->option->setUse(Inject::class)
            ->setVisitors(new class($this->option) extends AbstractVisitor {
                public function leaveNode(Node $node): int|Node
                {
                    // if ($node instanceof ClassMethod) {
                    //     if ($node->name->toString() == 'getList') {
                    //         $node->name = new Identifier('lsit');
                    //     }
                    //     if (in_array($node->name->toString(), ['recovery', 'toggle', 'change'])) {
                    //         return NodeTraverser::REMOVE_NODE;
                    //     }
                    // }

                    // if ($node instanceof Param) {
                    //     if ($node->var->name == 'item') {
                    //         return NodeTraverser::REMOVE_NODE;
                    //     }
                    // }

                    return $node;
                }
            }, 1);
        ! class_exists($this->option->getClass()) && $this->getMethodNodes();
        // $this->getMethodNodes();
    }

    protected function before(): void
    {
    }

    protected function after(): void
    {
        try {
            // code
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function getMethodNodes(): void
    {
        $code = '
        <?php
        class Logic
        {
            protected $instance;

            /**
             * 读取多条数据.
             */
            public function list(
                ListQuery $query,
                Search $search,
                Page $page,
                BaseSort $sort,
                ItemResponse $item
            ): ListResponse {
                return new ListResponse($this->instance->list(
                    $query,$search,$sort,$page, $item
                ));
            }

            /**
             * 写入数据.
             */
            public function create(
                CreateQuery $query,
                CreateData $data
            ): SuccessResponse {
                return new SuccessResponse($this->instance->create($query, $data));
            }

            /**
             * 更新数据.
             */
            public function update(
                UpdateQuery $query,
                UpdateData $data,
            ): SuccessResponse
            {
                $this->instance->update($query, $data);
                return new SuccessResponse();
            }

            /**
             * 读取数据.
             */
            public function detail(DetailQuery $query, ItemResponse $item): DetailResponse
            {
                return new DetailResponse($this->instance->detail($query));
            }

            /**
             * 删除数据.
             */
            public function delete(DeleteQuery $query): SuccessResponse
            {
                $this->instance->delete($query);

                return new SuccessResponse();
            }
            /**
             * 恢复数据
             */
            public function recovery(RecoveryQuery $query): SuccessResponse
            {
                $this->instance->recovery($query);
                return new SuccessResponse();
            }

            /**
             * 切换状态
             */
            public function change(ChangeQuery $query): SuccessResponse
            {
                $this->instance->change($query);
                return new SuccessResponse();
            }

            /**
             * 更新值
             */
            public function toggle(ToggleQuery $query): SuccessResponse
            {
                $this->instance->toggle($query);
                return new SuccessResponse();
            }
        }';
        $stmts = $this->parser->parse($code);
        // var_dump($stmts);exit;
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($this->option, $this->relation) extends AbstractVisitor {
            public function __construct(
                GenOption $option,
                protected array $relation
            ) {
                parent::__construct($option);
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Name) {
                    if (array_key_exists($node->toString(), $this->relation)) {
                        $this->option->setUse($this->relation[$node->toString()]);

                        return new Name($this->getClassName($this->relation[$node->toString()]));
                    }
                }
                if ($node instanceof ClassMethod) {
                    $this->option->setMethod($node->name->toString(), $node);
                }
            }
        });
        $stmts = $traverser->traverse($stmts);
    }
}
