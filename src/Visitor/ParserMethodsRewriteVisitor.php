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

use Mine\ComposerManager;
use Hyperf\Stringable\Str;
use Mine\Utils\Utils;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\UnionType;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;

use function Hyperf\Support\value;

class ParserMethodsRewriteVisitor extends AbstractVisitor
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
        protected array $relation = [],
    ) {
        parent::__construct($option);
        $factory = new ParserFactory();
        $this->parser = $factory->create(ParserFactory::ONLY_PHP7);
        $this->parserAllNode($class);
        // var_dump($this->parserMethods);
    }

    public function leaveNode(Node $node)
    {
        switch ($node) {
            case $node instanceof Namespace_:
                foreach ($this->parserUses as $use => $alias) {
                    $class = array_key_exists($use, $this->relation) ? $this->relation[$use] : $use;
                    if (! array_key_exists($class, $this->uses)) {
                        array_unshift($node->stmts, new Use_([
                            new UseUse(new Name($class), $alias ? new Name($alias) : $alias),
                        ]));
                    }
                }

                return $node;
            case $node instanceof Class_:
                // $this->rewriteMethods();
                $node->stmts = $this->rewriteMethods();

                return $node;
            case $node instanceof UseUse:
                return $node;
            default:
                return $node;
        }

        return $node;
    }

    protected function rewriteMethods(): ?array
    {
        $methods = [];
        foreach ($this->parserMethods as $method => &$node) {
            if (in_array($method, ['closure'])) {
                continue;
            }
            foreach ($node->getParams() as &$params) {
                if ($params->type instanceof Name) {
                    foreach ($this->relation as $key => $item) {
                        if (Str::endsWith($key, $params->type->toString())) {
                            // var_dump($type->toString());
                            $params->type = new Name(Utils::getClassBaseName($item));
                        }
                    }
                }
                if ($params->type instanceof UnionType) {
                    foreach ($params->type->types as &$type) {
                        foreach ($this->relation as $key => $item) {
                            if (Str::endsWith($key, $type->toString())) {
                                // var_dump($type->toString());
                                $type = new Name(Utils::getClassBaseName($item));
                            }
                        }
                    }
                }
            }
            // $this->rewriteMethodType($node);
            $node->stmts[] = $this->rewriteMethodArgs($method, $node);
            $methods[$method] = $node;
        }

        return $methods;
    }

    protected function rewriteMethodType(ClassMethod &$node): ?array
    {
        $methods = [];
        // todo: 弃用
        foreach ($this->parserMethods as $method => &$node) {
            foreach ($node->getParams() as &$params) {
                if ($params->type instanceof Name) {
                    foreach ($this->relation as $key => $item) {
                        if (Str::endsWith($key, $params->type->toString())) {
                            // var_dump($type->toString());
                            $params->type = new Name(Utils::getClassBaseName($item));
                        }
                    }
                }
                if ($params->type instanceof UnionType) {
                    foreach ($params->type->types as &$type) {
                        foreach ($this->relation as $key => $item) {
                            if (Str::endsWith($key, $type->toString())) {
                                // var_dump($type->toString());
                                $type = new Name(Utils::getClassBaseName($item));
                            }
                        }
                    }
                }
            }
            $node->stmts[] = $this->rewriteMethodArgs($method, $node);
            $methods[$method] = $node;
        }

        return $methods;
    }

    protected function rewriteMethodArgs(string $method, ClassMethod &$node): Return_
    {
        $relation = $this->relation;

        return new Return_(
            new MethodCall(
                new PropertyFetch(new Variable('this'), 'instance'),
                $method,
                value(static function () use ($node): array {
                    $args = [];
                    foreach ($node->getParams() as &$params) {
                        $args[] = new Arg($params->var);
                    }

                    return $args;
                })
            )
        );
    }

    /**
     * @return array[] [$uses, $propertys, $methods, $trautUses]
     */
    protected function parserAllNode(object|string $class): void
    {
        $file = ComposerManager::findFile(is_object($class) ? $class::class : $class);
        $reflectionClass = new ReflectionClass($class);
        if (! $file) {
            $file = $reflectionClass->getFileName();
        }

        $code = file_get_contents($file);
        [$uses, $propertys, $methods, $trautUses] = $this->getAllFromStmts($this->parser->parse($code), true);
        $this->parserUses = array_merge($this->parserUses, $uses);
        $this->parserProperties = array_merge($this->parserProperties, $propertys);
        $this->parserMethods = array_merge($this->parserMethods, $methods);
        $this->parserTraitUses = array_merge($this->parserTraitUses, $trautUses);
    }
}
