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

use Mine\Utils\Utils;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

abstract class AbstractVisitor extends NodeVisitorAbstract
{
    /**
     * @var UseUse[]<string,UseUse>
     */
    protected array $uses = [];

    /**
     * @var Property[]<string,Property>
     */
    protected array $properties = [];

    /**
     * @var ClassMethod[]<string,ClassMethod>
     */
    protected array $methods = [];

    /**
     * @var TraitUse[]<string,TraitUse>
     */
    protected array $traitUses = [];

    /**
     * @var Enum_[]<string,Enum_>
     */
    protected array $enums = [];

    public function __construct(
        protected GenOption $option
    ) {
    }

    public function beforeTraverse(array $nodes): ?array
    {
        [$uses, $properties, $methods, $traitUses] = $this->getAllFromStmts($nodes);
        $this->uses = $uses;
        $this->properties = $properties;
        $this->methods = $methods;
        $this->traitUses = $traitUses;

        return $nodes;
    }

    public function handleLeaveNode(Node $node): null|int|Node
    {
        switch ($node) {
            case $node instanceof Interface_:
            case $node instanceof Class_:
                if ($node instanceof Class_ || $node instanceof Interface_) {
                    if ($inheritance = $this->option->getInheritance()) {
                        $node->extends = new Name($this->getClassName(
                            $this->option->getInheritanceAlias() ?: $inheritance
                        ));
                        if (! $this->option->hasUse($inheritance)) {
                            $this->option->setUse($inheritance, $this->option->getInheritanceAlias());
                        }
                    }
                    if ($attributes = $this->option->getAttributes() && count($attributes ?? [])) {
                        $node->attrGroups = $attributes;
                    }
                } elseif (! $node instanceof ClassConst && $node->attrGroups) {
                    $node->attrGroups = [];
                }

                return $node;
            case $node instanceof Use_:
                assert($node instanceof Use_);

                return NodeTraverser::REMOVE_NODE;
            case $node instanceof Namespace_:
                assert($node instanceof Namespace_);
                array_unshift($node->stmts, ...$this->getUseNodes());

                return $node;
        }

        return $node;
    }

    protected function getClassName($class)
    {
        return Utils::getClassBaseName($class);
    }

    protected function getAllFromStmts(array $nodes, bool $skipMagic = false): array
    {
        $uses = [];
        $constants = [];
        $traitUses = [];
        $properties = [];
        $methods = [];
        $enums = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Namespace_) {
                continue;
            }

            foreach ($node->stmts as $stmt) {
                switch ($stmt) {
                    // case $stmt instanceof ClassConst:
                    //     assert($stmt instanceof ClassConst);
                    //     $constants[] = $stmt;
                    //     break;
                    // case $stmt instanceof Property:
                    //     assert($stmt instanceof Property);
                    //     $properties[] = $stmt;
                    //     break;
                    // case $stmt instanceof TraitUse:
                    //     assert($stmt instanceof TraitUse);
                    //     $traitUses[] = $stmt;
                    //     break;
                    // case $stmt instanceof ClassMethod:
                    //     assert($stmt instanceof ClassMethod);
                    //     $methods[] = $stmt;
                    //     break;
                    case $stmt instanceof Enum_:
                        assert($stmt instanceof Enum_);
                        $enums[] = $stmt;

                        break;
                    case $stmt instanceof Class_:
                    case $stmt instanceof Interface_:
                    case $stmt instanceof Trait_:
                        assert($stmt instanceof ClassLike);
                        foreach ($stmt->getConstants() as $constant) {
                            assert($constant instanceof ClassConst);
                            $constants[] = $constant;
                        }

                        foreach ($stmt->getProperties() as $property) {
                            assert($property instanceof Property);
                            // \PhpParser\Node\Stmt\PropertyProperty::class;
                            $name = $property->props[0]->name->toString();
                            // dump($property);exit;
                            $properties[$name] = $property;
                        }

                        foreach ($stmt->getTraitUses() as $trait) {
                            assert($trait instanceof TraitUse);

                            $traitUses[] = $trait;
                        }

                        foreach ($stmt->getMethods() as $method) {
                            assert($method instanceof ClassMethod);
                            if (($skipMagic && $method->isMagic()) || isset($methods[$method->name->toString()])) {
                                continue;
                            }

                            $methods[$method->name->toString()] = $method;
                        }

                        break;
                    case $stmt instanceof Use_:
                        assert($stmt instanceof Use_);
                        foreach ($stmt->uses as $use) {
                            assert($use instanceof UseUse);
                            if (isset($uses[$use->name->toString()])) {
                                continue;
                            }
                            if ($use->type == 0) {
                                $class = $use->name->toString();
                                $alias = is_object($use->alias) ? $use->alias->toString() : null;
                                $uses[$class] = $alias;
                                $this->option->setUse($class, $alias);
                            }
                        }

                        break;
                    default:
                        break;
                }
            }
        }

        return [$uses, $properties, $methods, $traitUses];
    }

    protected function getUseNodes(): array
    {
        $stmts = [];
        foreach ($this->option->getUses() as $class => $alias) {
            if ($class == $this->option->getClass()) {
                continue;
            }
            if ($this->getClassName($class) == $alias) {
                $alias = null;
            }
            $stmts[] = new Use_([new UseUse(new Name($class), $alias ? new Name($alias) : $alias)]);
            $this->option->removeUse($class);
        }

        return $stmts;
    }
}
