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

namespace Mine\Devtool\Visitor;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use Throwable;

class GenVisitor extends AbstractVisitor
{
    protected ?string $inheritance = null;

    protected bool $shouldAddUseUse = true;

    public function enterNode(Node $node): null|int|Node
    {
        try {
            // todo: 待重构为节点替换
            switch ($node) {
                case $node instanceof Class_:
                case $node instanceof Interface_:
                    foreach ($node->stmts as $key => &$method) {
                        if ($method instanceof ClassConst) {
                            assert($method instanceof ClassConst);
                            foreach ($method->consts as $const) {
                                $this->rewriteConst($const);
                            }
                        }

                        if ($method instanceof ClassMethod) {
                            assert($method instanceof ClassMethod);
                            $method = $this->rewriteMethod($method);
                        }

                        if ($method instanceof Property) {
                            assert($method instanceof Property);
                            $method = $this->rewriteProperty($method);
                        }

                        if ($method instanceof TraitUse) {
                            assert($method instanceof TraitUse);
                            if ($this->rewriteTraits($method)) {
                                unset($node->stmts[$key]);
                            }
                        }
                    }

                    if (isset($node->implements)) {
                        foreach ($node->implements as $implement) {
                            $this->rewriteInterface($implement);
                        }
                    }

                    array_push($node->stmts, ...array_values($this->option->getMethods()));
                    array_unshift($node->stmts, ...$this->option->getPropertyByValues() ?? []);
                    array_unshift($node->stmts, ...$this->option->getTraitUses() ?? []);

                    return $node;
            }

            return $node;
        } catch (Exception $exception) {
            throw $exception;
        } catch (Throwable  $throwable) {
            throw $throwable;
        }
    }

    public function leaveNode(Node $node): null|int|Node
    {
        try {
            $node = $this->handleLeaveNode($node);
            switch ($node) {
                // todo: 合并现有
                case $node instanceof Interface_:
                case $node instanceof Class_:
                    foreach ($this->option->getImplements() as $interface => $alias) {
                        $this->option->setUse($interface, $alias);
                        array_unshift($node->implements, new Name($this->getClassName($alias ?: $interface)));
                    }

                    return $node;
            }

            return $node;
        } catch (Exception $exception) {
            throw $exception;
        } catch (Throwable  $throwable) {
            throw $throwable;
        }
    }

    public function afterTraverse(array $nodes)
    {
        return $nodes;
    }

    protected function rewriteInterface(?Name $node = null): ?Name
    {
        foreach ($this->option->getImplements() as $class => $alias) {
            if ($class == $node->toString() || $this->getClassName(
                $class
            ) == $node->toString() || $alias == $node->toString()) {
                $this->option->removeImplements($class);
            }
        }

        return $node;
    }

    protected function rewriteConst(?Const_ $node = null): ?Const_
    {
        foreach ($this->option->getMethods() as $name => $method) {
            if (! ($method instanceof ClassConst)) {
                continue;
            }

            foreach ($method->consts as $const) {
                if ($const?->name?->name == $node?->name?->name) {
                    $this->option->removeMethod($name);
                }
            }
        }

        return $node;
    }

    protected function rewriteMethod(?ClassMethod $node = null): ?ClassMethod
    {
        foreach ($this->option->getMethods() as $name => $method) {
            if (! ($method instanceof ClassMethod)) {
                continue;
            }

            if ($method?->name?->name == $node?->name?->name) {
                if (in_array($node?->name?->name, ['__construct', '__invoke'])) {
                    $node = $method;
                }

                $this->option->removeMethod($name);
            }
        }

        return $node;
    }

    protected function rewriteTraits(TraitUse $node = null): bool
    {
        foreach ($this->option->getTraitUses() as $key => $method) {
            foreach ($node->traits as $trait) {
                if ($trait->getLast() == $method->traits[0]->getLast()) {
                    return true;
                    //  $this->option->removeNodes($key);
                }
            }
        }

        return false;
    }

    protected function rewriteProperty(?Property $node = null): ?Property
    {
        foreach ($this->option->getpropertys() as $name => $property) {
            if ($property->props[0]->name->name == $node->props[0]->name->name) {
                $node = $property;
                $this->option->removeproperty($name);
            }
        }

        return $node;
    }
}
