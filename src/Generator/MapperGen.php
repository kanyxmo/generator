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
use Hyperf\Database\Model\Builder;
use Mine\Abstract\AbstractMapper;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

class MapperGen extends AbstractGenerator
{
    protected function configure(): void
    {
        $this->inheritance = (new ModelGen($this->current_app, $this->name))->qualifyClass();
        // $this->option->setVisitors(new class extends NodeVisitorAbstract {
        //     public function leaveNode(Node $node)
        //     {
        //         if ($node instanceof ClassMethod) {
        //             return NodeTraverser::REMOVE_NODE;
        //         }

        //         return $node;
        //     }
        // });
        $this->option->setUse($this->inheritance)
            ->setUse(Builder::class)->setInheritance(AbstractMapper::class)
            ->setUse(AbstractMapper::class)
            ->setMethod('__construct', $this->factory->method('__construct')
                ->makePublic()
                ->addParam(
                    new Param(new Variable('instance'), null, new Identifier($this->getClassName(
                        $this->inheritance
                    )), false, false, [], Class_::MODIFIER_PROTECTED)
                )
                ->getNode());
    }

    protected function after(): void
    {
        try {
            (new ServiceGen($this->current_app, $this->name))->generate();
        } catch (Exception $exception) {
            throw $exception;
        }
    }
}
