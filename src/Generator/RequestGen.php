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

use Mine\Devtool\Visitor\AbstractVisitor;
use Mine\Devtool\Visitor\PropertiesVisitor;
use Mine\DTO\Annotation\Property;
use Mine\Request\BaseRequest;
use Hyperf\Stringable\Str;
use PhpParser\Node\Stmt\Property as StmtProperty;
use PhpParser\NodeTraverser;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

class RequestGen extends AbstractGenerator
{
    protected ?array $properties = [];

    protected function configure(): void
    {
        if ($this->inheritance == null) {
            $this->inheritance = BaseRequest::class;
        }
        $this->option->setUse(Property::class);
        $this->option->setUse($this->inheritance)
            ->setInheritance($this->inheritance)
            ->setUse(Property::class)
            ->setVisitors(new PropertiesVisitor($this->option, $this->properties, excludeType: ['json', 'array']), 100)
            ->setVisitors(new class($this->option) extends AbstractVisitor {
                public function leaveNode(Node $node): int|Node
                {
                    $this->option->clearMethods();
                    if ($node instanceof StmtProperty) {
                        if (Str::endsWith($this->option->getClass(), 'Data') && in_array(
                            $node->props[0]->name->toString(),
                            [
                                'memberId',
                                'createdId',
                                'updatedId',
                                'deletedId',
                                'createdTime',
                                'updatedTime',
                                'deletedTime',
                            ]
                        )) {
                            return NodeTraverser::REMOVE_NODE;
                        }
                    }

                    return $node;
                }
            }, 9999);
    }
}
