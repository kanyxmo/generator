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

use Hyperf\CodeParser\PhpParser;
use Hyperf\Stringable\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

use function Hyperf\Support\getter;
use function Hyperf\Support\setter;
use function Hyperf\Support\value;

class GetterSetterVisitor extends AbstractVisitor
{
    /**
     * @var array
     */
    protected array $getters = [];

    /**
     * @var array
     */
    protected array $setters = [];

    /**
     * @var array
     */
    protected array $attres = [];

    /**
     * @var array
     */
    protected array $stmts = [];

    public function __construct(GenOption $option)
    {
        parent::__construct($option);
        $class = $option->getClass();
        if ($class && class_exists($class)) {
            $reflect = new ReflectionClass(new $class());
            $props = $reflect->getProperties();
            foreach ($props as $prop) {
                if (! $prop->isReadOnly()) {
                    $this->stmts[] = getter($prop->getName());
                    $this->stmts[] = setter($prop->getName());
                    $type = [];
                    $allowsNull = false;
                    if ($prop->hasType() && $prop->getType() instanceof ReflectionUnionType || $prop->getType() instanceof ReflectionIntersectionType) {
                        foreach ($prop->getType()->getTypes() as $ptype) {
                            $type[] = $ptype->isBuiltin() ? $ptype->getName() : sprintf('\%s', $ptype->getName());
                            $allowsNull = $ptype->allowsNull();
                        }
                    } elseif ($prop->hasType() && $prop->getType() instanceof ReflectionNamedType) {
                        $type = $prop->getType()
                            ->isBuiltin() ? $prop->getType()
                            ->getName() : sprintf('\%s', $prop->getType()->getName());
                        $allowsNull = $prop->getType()
                            ->allowsNull();
                    } else {
                        $type = 'mixed';
                    }

                    $this->attres[] = [
                        'name' => $prop->getName(),
                        'value' => $prop->hasDefaultValue() ? $prop->getDefaultValue() : false,
                        'type' => $type,
                        'allowsNull' => $allowsNull,
                    ];
                }
            }
        }
    }

    public function beforeTraverse(array $nodes): null|array
    {
        $methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);

        $this->collectMethods($methods);

        return $nodes;
    }

    public function enterNode(Node $node): Node
    {
        if ($node instanceof Class_ || $node instanceof Interface_) {
            foreach ($node->stmts as $key => $method) {
                if ($method instanceof ClassMethod) {
                    $methodName = $method->name->name;
                    if (! in_array($methodName, $this->stmts)) {
                        unset($node->stmts[$key]);
                    }
                }
            }
        }

        return $node;
    }

    public function afterTraverse(array $nodes): null|array
    {
        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (! $class instanceof Class_) {
                    continue;
                }

                array_push($class->stmts, ...$this->buildGetterAndSetter());
            }
        }

        return $nodes;
    }

    /**
     * @return Node\Stmt\ClassMethod[]
     */
    protected function buildGetterAndSetter(): array
    {
        $stmts = [];
        foreach ($this->attres as $attr) {
            if ($attr['name']) {
                $getter = getter($attr['name']);
                if (! in_array($getter, $this->getters)) {
                    $stmts[] = $this->createGetter(
                        $getter,
                        $attr['name'],
                        $attr['type'],
                        $attr['allowsNull'],
                        $attr['value']
                    );
                }

                $setter = setter($attr['name']);
                if (! in_array($setter, $this->setters)) {
                    $stmts[] = $this->createSetter(
                        $setter,
                        $attr['name'],
                        $attr['type'],
                        $attr['allowsNull'],
                        $attr['value']
                    );
                }
            }
        }

        return $stmts;
    }

    protected function createGetter(string $method, string $name, $type, $allowsNull, $value): ClassMethod
    {
        $node = new ClassMethod($method, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => is_array($type) ? new UnionType(
                value(static function () use ($type): array {
                    $types = [];
                    foreach ($type as $t) {
                        $types[] = new Identifier($t);
                    }

                    return $types;
                })
            ) : value(
                static fn (): NullableType|Identifier => $allowsNull ? new NullableType(new Identifier(
                    $type
                )) : new Identifier($type)
            ),
        ]);
        $node->stmts[] = new Return_(new PropertyFetch(new Variable('this'), new Identifier($name)));

        return $node;
    }

    protected function createSetter(string $method, string $name, $type, $allowsNull, $value): ClassMethod
    {
        // $type =

        $node = new ClassMethod($method, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [new Param(
                new Variable($name),
                $allowsNull ? new ConstFetch(new Name('null')) : null,
                is_array($type) ? new UnionType(
                    value(static function () use ($type): array {
                        $types = [];
                        foreach ($type as $t) {
                            $types[] = new Identifier($t);
                        }

                        return $types;
                    })
                ) : value(
                    static fn (): NullableType|Identifier => $allowsNull ? new NullableType(new Identifier(
                        $type
                    )) : new Identifier($type)
                )
            )],
            'returnType' => new Name('static'),
        ]);
        $node->stmts[] = new Expression(
            new Assign(new PropertyFetch(new Variable('this'), new Identifier($name)), new Variable($name))
        );

        $node->stmts[] = new Return_(new Variable('this'));

        return $node;
    }

    protected function collectMethods(array &$methods): void
    {
        /** @var Node\Stmt\ClassMethod $method */
        foreach ($methods as $method) {
            $methodName = $method->name->name;
            if (Str::startsWith($methodName, 'get')) {
                $this->getters[] = $methodName;
            } elseif (Str::startsWith($methodName, 'set')) {
                $this->setters[] = $methodName;
            }
        }
    }
}
