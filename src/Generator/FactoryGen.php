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
use Mine\Abstract\AbstractFactory;
use Mine\Devtool\Visitor\RewriteMethodsCommitVisitor;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};
use Psr\SimpleCache\CacheInterface;

class FactoryGen extends AbstractGenerator
{
    protected function configure(): void
    {
        try {
            $this->option->setInheritance(AbstractFactory::class)
                ->setUse(AbstractFactory::class)
                ->setUse(CacheInterface::class);
            if ($this->inheritance && class_exists($this->inheritance)) {
                $this->option->setUse($this->inheritance);
                // ->setVisitors(new RewriteMethodsCommitVisitor($this->inheritance, true),10);
            }

            $this->option->setMethod('__invoke', new ClassMethod('__invoke', [
                'flags' => Class_::MODIFIER_PUBLIC,
                'returnType' => $this->inheritance ? new Param(new Variable($this->getClassName(
                    $this->inheritance
                ))) : null,
                'stmts' => [
                    new Expression(
                        new Assign(new Variable('container'), new FuncCall(new Name('container')))
                    ), new Expression(
                        new Assign(
                            new Variable('cache'),
                            new MethodCall(
                                new Variable('container'),
                                new Identifier('get'),
                                [new Arg(new ClassConstFetch(new Name('CacheInterface'), new Identifier('class')))],
                            ),
                        )
                    ),
                    $this->inheritance && new Return_(
                        new FuncCall(new Name('make'), [
                            new Arg(new ClassConstFetch(new Name($this->getClassName(
                                $this->inheritance
                            )), new Identifier('class'))),
                            new Arg(
                                new FuncCall(
                                    new Name('compact'),
                                    [new Arg(new String_('container')), new Arg(new String_('cache'))]
                                )
                            ),
                        ])
                    ),
                ],
            ]));
        } catch (Exception $exception) {
            throw $exception;
        }
    }
}
