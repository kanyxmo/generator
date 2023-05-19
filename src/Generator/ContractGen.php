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

use Mine\Abstract\BaseObject;
use Mine\Devtool\Visitor\AbstractVisitor;
use Mine\Devtool\Visitor\GenOption;
use Mine\Devtool\Visitor\ParserClassMethodAddrVisitor;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

class ContractGen extends AbstractGenerator
{
    protected function configure(): void
    {
        $this->option->setUse(BaseObject::class)->setVisitors(new class() extends NodeVisitorAbstract {
            public function leaveNode(Node $node)
            {
                if ($node instanceof ClassMethod) {
                    return NodeTraverser::REMOVE_NODE;
                }

                return $node;
            }
        })->setType(GenOption::TYPE_INTERFACE)
            ->setVisitors(new class($this->option) extends AbstractVisitor {
                public function leaveNode(Node $node): int|Node
                {
                    switch ($node) {
                        case $node instanceof ClassMethod:
                            $node->stmts = null;

                            return $node;
                        case $node instanceof Attribute:
                            if (in_array('Transaction', $node->name->parts)) {
                                return NodeTraverser::REMOVE_NODE;
                            }

                            return $node;
                        case $node instanceof AttributeGroup:
                            if (count($node->attrs) == 0) {
                                return NodeTraverser::REMOVE_NODE;
                            }

                            return $node;
                    }

                    return $node;
                }
            }, 999);
        if (! $this->inheritance) {
            $this->inheritance = (new ServiceGen($this->current_app, $this->name))->qualifyClass();
        }

        if (class_exists($this->inheritance) || interface_exists($this->inheritance)) {
            $this->option->setVisitors(
                new ParserClassMethodAddrVisitor($this->option, $this->inheritance, true, true),
                998
            );
        }
        $this->option->removeUse($this->inheritance);
    }
}
