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

use Mine\Devtool\Visitor\PropertiesVisitor;
use Mine\DTO\Annotation\Property;
use Mine\Response\BaseResponse;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

class ResponseGen extends AbstractGenerator
{
    protected ?array $properties = [];

    protected function configure(): void
    {
        if ($this->inheritance == null) {
            $this->inheritance = BaseResponse::class;
        }
        $this->option->setUse($this->inheritance)
            ->setInheritance($this->inheritance)
            ->setUse(Property::class)
            ->setVisitors(
                new PropertiesVisitor($this->option, $this->properties, [
                    'created_id',
                    'updated_id',
                    'deleted_id',
                    'created_time',
                    'updated_time',
                    'deleted_time',
                ]),
                100
            );
    }
}
