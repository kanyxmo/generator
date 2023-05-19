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
use Hyperf\Database\Commands\Ast\AbstractVisitor;
use Hyperf\Stringable\Str;
use Mine\Utils\Utils;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;

use function Hyperf\Support\getter;
use function Hyperf\Support\setter;

class ModelGetterSetterVisitor extends AbstractVisitor
{
    /**
     * @var array
     */
    protected array $getters = [];

    /**
     * @var array
     */
    protected array $setters = [];

    public function beforeTraverse(array $nodes): array|null
    {
        $methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);
        $this->collectMethods($methods);

        return $nodes;
    }

    public function leaveNode(Node $node): Node|int
    {
        if ($node instanceof ClassMethod) {
            $methodName = $node->name->name;
            if (in_array($methodName, $this->setters)) {
                return (int) NodeTraverser::REMOVE_NODE;
            }
            if (in_array($methodName, $this->getters)) {
                return (int) NodeTraverser::REMOVE_NODE;
            }
        }

        return $node;
    }

    public function afterTraverse(array $nodes): array|null
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
        foreach ($this->data->getColumns() as $column) {
            if ($name = $column['column_name'] ?? null) {
                $getter = getter($name);
                if (! in_array($getter, $this->getters)) {
                    $stmts[] = $this->createGetter($getter, $name, $column['column_comment'], $column['vo'] ?? null);
                    if ($column['vo']) {
                        $stmts[] = $this->createGetter(
                            $getter . 'Attribute',
                            $name,
                            $column['column_comment'],
                            $column['vo'] ?? null
                        );
                    }
                }

                $setter = setter($name);
                if (! in_array($setter, $this->setters)) {
                    $stmts[] = $this->createSetter($setter, $name, $column['column_comment'], $column['vo'] ?? null);
                    if ($column['vo']) {
                        $stmts[] = $this->createSetter(
                            $setter . 'Attribute',
                            $name,
                            $column['column_comment'],
                            $column['vo'] ?? null
                        );
                    }
                }
            }
        }

        return [];
    }

    protected function createGetter(string $method, string $name, ?string $comment, ?string $vo): ClassMethod
    {
        $comment = $comment ?: $name;

        if (Str::endsWith($method, 'Attribute')) {
            $node = new ClassMethod($method, [
                'flags' => Class_::MODIFIER_PUBLIC,
                'params' => [new Param(new Variable('value'))],
            ], [
                'comments' => [new Doc("/**\n     * {$comment}获取器\n     */")],
            ]);
            $node->stmts[] = new Return_(
                new New_(new Name(Utils::getClassBaseName($vo)), [new Arg(new Variable('value'))])
            );
        } else {
            $node = new ClassMethod($method, [
                'flags' => Class_::MODIFIER_PUBLIC,
            ], [
                'comments' => [new Doc("/**\n     * 获取{$comment}\n     */")],
            ]);
            $node->stmts[] = new Return_(new PropertyFetch(new Variable('this'), new Identifier($name)));
        }

        return $node;
    }

    protected function createSetter(string $method, string $name, ?string $comment, ?string $vo): ClassMethod
    {
        $comment = $comment ?: $name;

        if (Str::endsWith($method, 'Attribute')) {
            $node = new ClassMethod($method, [
                'flags' => Class_::MODIFIER_PUBLIC,
                'params' => [new Param(new Variable('value'))],
            ], [
                'comments' => [new Doc("/**\n     * {$comment}修改器\n     */")],
            ]);
            $node->stmts[] = new Expression(
                new Assign(
                    new ArrayDimFetch(
                        new PropertyFetch(new Variable('this'), new Identifier('attributes')),
                        new String_($name)
                    ),
                    new New_(new Name(Utils::getClassBaseName($vo)), [new Arg(new Variable('value'))])
                )
            );
        } else {
            $node = new ClassMethod($method, [
                'flags' => Class_::MODIFIER_PUBLIC,
                'params' => [new Param(new Variable($name))],
                'returnType' => new Name('static'),
            ], [
                'comments' => [new Doc("/**\n     * 设置{$comment}\n     */")],
            ]);
            $node->stmts[] = new Expression(
                new Assign(new PropertyFetch(new Variable('this'), new Identifier($name)), new Variable($name))
            );
        }

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
