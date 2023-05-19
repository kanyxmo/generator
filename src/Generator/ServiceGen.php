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
use Hyperf\Di\Annotation\Inject;
use Mine\Abstract\AbstractService;
use Mine\Annotation\Definition;
use Mine\Gateway\RpcServer\Annotation\RpcService;
use Hyperf\Stringable\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class ServiceGen extends AbstractGenerator
{
    protected ?array $extra_implements = [];

    protected function configure(): void
    {
        if (! $this->implements) {
            $this->implements = (new ContractGen($this->current_app, $this->name))->qualifyClass();
        }
        foreach ($this->extra_implements as $implement) {
            $this->option->setUse($implement)
                ->setImplements($implement);
        }
        $inheritance = (new MapperGen($this->current_app, $this->name));
        $this->inheritance = $inheritance->qualifyClass();
        if ($this->inheritance && class_exists($this->inheritance)) {
            $this->option->setUse($this->inheritance)
                ->setInheritance(AbstractService::class);
            $this->option->setMethod('__construct', new ClassMethod('__construct', [
                'flags' => Class_::MODIFIER_PUBLIC,
                'params' => [
                    new Param(new Variable('instance'), null, new Identifier($this->getClassName(
                        $this->inheritance
                    )), false, false, [], Class_::MODIFIER_PROTECTED),
                ],
            ]));
        }

        $pkgName = Str::replace(['_', '-'], '', Str::replace(['::', ':'], '.', $this->getIdentifier($this->name)));
        $subClassName = $this->getClassName($this->implements);
        $this->option->setUse(Definition::class)
            ->setUse($this->implements)
            ->setImplements($this->implements)
            ->setAttribute('Definition', new AttributeGroup([
                new Attribute(
                    new Name('Definition'),
                    [new Arg(new Array_([
                        new ArrayItem(new String_($subClassName)),
                        new ArrayItem(new String_($this->getClassName($this->qualifyClass()))),
                        new ArrayItem(new String_($pkgName)),
                        new ArrayItem(new ClassConstFetch(new Name($subClassName), new Identifier('class'))),
                    ]), false, false, [], new Identifier('values'))]
                ),
            ]))->setUse(AbstractService::class)
            ->setUse(ContainerInterface::class)
            ->setUse(CacheInterface::class)
            ->setUse(Inject::class)
            ->setUse(RpcService::class)
        // ->setAttribute('RpcService', new AttributeGroup([new Attribute(new Name('RpcService'), [new Arg(new String_($pkgName), false, false, [], new Identifier('name'))])]))
            ->setProperty('container', new Property(
                Class_::MODIFIER_PROTECTED,
                [new PropertyProperty('container', null)],
                [],
                new Identifier($this->getClassName(ContainerInterface::class)),
                [new AttributeGroup([new Attribute(new Name('Inject'))])]
            ))
            ->setProperty('cache', new Property(
                Class_::MODIFIER_PROTECTED,
                [new PropertyProperty('cache', null)],
                [],
                new Identifier($this->getClassName(CacheInterface::class)),
                [new AttributeGroup([new Attribute(new Name('Inject'))])]
            ));
    }

    protected function before(): void
    {
        $this->implements = (new ContractGen($this->current_app, $this->name))->generate();
        $this->configure();
    }

    protected function after(): void
    {
        try {
            (new ContractGen($this->current_app, $this->name, inheritance: $this->option->getClass()))->generate();
            // (new FactoryGen($this->current_app, $this->name))->generate();
        } catch (Exception $exception) {
            throw $exception;
        }
    }
}
