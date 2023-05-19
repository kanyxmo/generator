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
use Hyperf\Stringable\Str;
use Mine\ComposerManager;
use Mine\Devtool\Generator\Rector;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;
use Throwable;

class ParserClassMethodAddrVisitor extends AbstractVisitor
{
    /**
     * @var UseUse[]
     */
    protected array $parserUses = [];

    /**
     * @var Property[]
     */
    protected array $parserProperties = [];

    /**
     * @var ClassMethod[]
     */
    protected array $parserMethods = [];

    /**
     * @var TraitUse[]
     */
    protected array $parserTraitUses = [];

    protected Parser $parser;

    public function __construct(
        GenOption $option,
        protected string $class,
        protected bool $paarent = false,
        protected bool $traut = false,
        protected int $handleType = 0,
    ) {
        parent::__construct($option);
        $factory = new ParserFactory();
        $this->parser = $factory->create(ParserFactory::ONLY_PHP7);
        $this->parserAllNode($class, $this->paarent);
    }

    public function enterNode(Node $node): null|int|Node
    {
        unset($this->parserUses[$this->option->getClass()]);

        try {
            switch ($node) {
                case $node instanceof Interface_:
                    assert($node instanceof ClassLike);
                    foreach ($node->stmts as $key => &$stmt) {
                        if ($stmt instanceof ClassMethod) {
                            unset($node->stmts[$key]);
                        }
                    }

                    foreach ($this->parserMethods as $method) {
                        if ($method->isPublic() && ! $method->isMagic()) {
                            unset($method->stmts);
                            $node->stmts[] = $method;
                        }
                    }

                    return $node;
                case $node instanceof Class_:
                case $node instanceof ClassConst:
                    return $node;
                case $node instanceof UseUse:
                    $uses = $this->parserUses;
                    assert($node instanceof UseUse);
                    if (array_key_exists($node->name->toString(), $uses)) {
                        $alias = ($node->alias === null || ($node->name->getLast() === $node->alias->toString())) ? null : $node->alias->toString();
                        $parserUsesAlias = $uses[$node->name->toString()];
                        if ($parserUsesAlias && ($parserUsesAlias !== $alias)) {
                            $node->alias = new Name($parserUsesAlias);
                        }

                        unset($uses[$node->name->toString()]);
                    }

                    $this->parserUses = $uses;

                    return $node;
                case $node instanceof Use_:
                    assert($node instanceof Use_);
                    // foreach ($node->uses as &$use) {
                    //     assert($use instanceof UseUse);
                    //     if (isset($this->parserUses[$use->name->toString()])) {
                    //         $alias = ($use->alias === null || ($use->name->getLast() === $use->alias->toString())) ? null : $use->alias->toString();
                    //         $parserUsesAlias = $this->parserUses[$use->name->toString()];
                    //         if ($parserUsesAlias && ($parserUsesAlias !== $alias)) {
                    //             $use->alias = new Node\Name($parserUsesAlias);
                    //         }
                    //         unset($this->parserUses[$use->name->toString()]);
                    //         continue;
                    //     }
                    // }
                    // no break
                default:
                    return $node;
            }

            return $node;
        } catch (Error $error) {
            throw $error;
        } catch (Exception $exception) {
            throw $exception;
        } catch (Throwable  $throwable) {
            throw $throwable;
        }
    }

    public function afterTraverse(array $nodes)
    {
        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Namespace_) {
                continue;
            }

            foreach ($this->parserUses as $use => $alias) {
                array_unshift($namespace->stmts, new Use_([
                    new UseUse(new Name($use), $alias ? new Name($alias) : $alias),
                ]));
            }
        }

        // (new Rector)->nodeTraverser(); // ->traverse([]);

        return $nodes;
    }

    public function getParserMethods(): array
    {
        return $this->parserMethods;
    }

    public function getParserUses(): array
    {
        return $this->parserUses;
    }

    public function getparserProperties(): array
    {
        return $this->parserProperties;
    }

    public function getparserTraitUses(): array
    {
        return $this->parserTraitUses;
    }

    /**
     * @return array[] [$uses, $propertys, $methods, $trautUses]
     */
    protected function parserAllNode(object|string $class, bool $paarent = false, bool $skipMagic = false): void
    {
        $file = ComposerManager::findFile(is_object($class) ? $class::class : $class);
        $reflectionClass = new ReflectionClass($class);
        if (! $file) {
            $file = $reflectionClass->getFileName();
        }

        $code = file_get_contents($file);
        [$uses, $propertys, $methods, $trautUses] = $this->getAllFromStmts($this->parser->parse($code), $skipMagic);
        $this->parserUses = array_merge($this->parserUses, $uses);
        $this->parserProperties = array_merge($this->parserProperties, $propertys);
        $this->parserMethods = array_merge($this->parserMethods, $methods);
        $this->parserTraitUses = array_merge($this->parserTraitUses, $trautUses);
        if ($paarent && $parentClassMap = $reflectionClass->getParentClass()) {
            foreach ($parentClassMap as $parentClass) {
                $this->parserAllNode($parentClass, false, true);
            }
        }

        if ($this->traut && $traitClassMap = $reflectionClass->getTraitNames()) {
            foreach ($traitClassMap as $traitClass) {
                if (Str::startsWith($traitClass, 'Hyperf')) {
                    continue;
                }

                $this->parserAllNode($traitClass, false, true);
            }
        }
    }
}
