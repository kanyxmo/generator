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

use Mine\Annotation\EnumView;
use Mine\DTO\Annotation\ArrayType;
use Mine\DTO\Annotation\Attribute;
use Mine\DTO\Annotation\Property;
use Hyperf\Stringable\Str;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute as NodeAttribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property as StmtProperty;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Trait_;

class PropertiesVisitor extends AbstractVisitor
{
    protected string $class;

    public function __construct(
        protected GenOption $option,
        protected array $properties_args,
        protected array $exclude = ['deleted_id', 'deleted_time'],
        protected array $excludeType = []
    ) {
        parent::__construct($option);
    }

    public function beforeTraverse(array $nodes): null|array
    {
        parent::beforeTraverse($nodes);
        foreach ($this->properties_args as &$item) {
            if (in_array($item['data_type'], ['array', 'json']) && $item['cast'] && class_exists($item['cast'])) {
                $item['data_type'] = $item['cast'];
            }
            if (class_exists($item['data_type'])) {
                $this->option->setUse($item['data_type']);
            }
            if (! empty($item['attributes'])) {
                foreach ($item['attributes'] as $class => $params) {
                    if (class_exists($class)) {
                        $this->option->setUse($class);
                    }
                    if ($class == ArrayType::class && class_exists($params[0])) {
                        $this->option->setUse(ArrayType::class);
                        $this->option->setUse($params[0]);
                    }
                }
            }
            $this->option->setUse(Property::class);
        }

        return $nodes;
    }

    public function leaveNode(Node $node): int|Node|null
    {
        $node = $this->handleLeaveNode($node);
        switch ($node) {
            case $node instanceof Class_:
            case $node instanceof Trait_:
                $node->setAttribute('comments', [new Comment(PHP_EOL)]);
                // $node->stmts[] = $this->addProperties();
                array_push($node->stmts, ...$this->addProperties());

                return $node;
        }

        return $node;
    }

    public function formatType(string $type): string
    {
        if (class_exists($type)) {
            return $this->getClassName($type);
        }

        return match ($type) {
            'tinyint', 'smallint', 'mediumint', 'int' => 'int',
            'bool', 'boolean' => 'bool',
            'array', 'json' => 'array',
            default => 'string',
        };
    }

    protected function addProperties(): array
    {
        $properties = [];
        foreach ($this->properties_args as $item) {
            if (in_array($item['column_name'], [...$this->exclude])) {
                continue;
            }
            if (in_array($item['data_type'], $this->excludeType)) {
                continue;
            }
            $name = Str::camel($item['column_name']);
            if (array_key_exists($name, $this->properties)) {
                continue;
            }
            // dump(array_keys($this->properties), $name);
            // exit;
            // dump($item['column_name']);
            $property = new StmtProperty(
                Class_::MODIFIER_PUBLIC,
                [new PropertyProperty($name)],
                [
                    'comments' => [new Comment(PHP_EOL)],
                ],
                // (($item['column_key'] == 'PRI') || in_array($item['data_type'], ['array', 'json'])) ? new NullableType(
                //     ($item['column_key'] == 'PRI') ? 'int' : $this->formatType($item['data_type'])
                // ) :
                $this->formatType($item['data_type'])
            );

            $autoFill = true;
            if (! empty($item['attributes'])) {
                foreach ($item['attributes'] as $class => $params) {
                    switch ($class) {
                        case Attribute::class:

                            $property->attrGroups[] = $this->generateApiAttributeAttribute(
                                $item['column_comment'],
                                $item['column_key'],
                                $item['data_type']
                            );
                            $autoFill = false;

                            break;

                        case EnumView::class:

                            $property->attrGroups[] = $this->generateEnumViewAttribute();

                            break;

                        case ArrayType::class:

                            $property->attrGroups[] = $this->generateArrayTypeAttribute(...$params);

                            break;
                    }
                }
            }
            if ($autoFill) {
                $property->attrGroups[] = $this->generatePropertyAttribute(
                    $item['column_comment'],
                    $item['column_key'],
                    $item['data_type']
                );
            }
            $properties[] = $property;
        }

        return $properties;
    }

    protected function generatePropertyAttribute(
        string $name,
        ?string $pri = null,
        ?string $dataType = 'string'
    ): AttributeGroup {
        $attrName = new Name($this->getClassName(Property::class));
        $args = [];
        $args[] = new Arg(new String_($name), false, false, []);
        $args[] = new Arg(new String_($this->formatOtherType($dataType)), false, false, []);
        if ($pri == 'PRI') {
            $args[] = new Arg(new ConstFetch(new Name('true')), false, false, []);
        }

        return new AttributeGroup([new NodeAttribute($attrName, $args)]);
    }

    protected function formatOtherType(?string $type = null): ?string
    {
        return match ($type) {
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => 'number',
            'decimal' => 'decimal:2',
            'float', 'double', 'real' => 'decimal',
            'bool', 'boolean' => 'boolean',
            'datetime', 'timestamp' => 'datetime:Y-m-d H:i:s',
            'date' => 'datetime:Y-m-d',
            'time' => 'datetime:H:i:s',
            'year' => 'datetime:Y',
            'json','array' => 'array',
            default => class_exists($type) ? 'object' : 'string',
        };
    }

    protected function generateApiAttributeAttribute(
        string $name,
        ?string $pri = null,
        ?string $dataType = 'string'
    ): AttributeGroup {
        $attrName = new Name($this->getClassName(Attribute::class));
        $args = [];
        $args[] = new Arg(new String_($name), false, false, []);
        $args[] = new Arg(new String_($this->formatOtherType($dataType)), false, false, []);
        if ($pri == 'PRI') {
            $args[] = new Arg(new ConstFetch(new Name('true')), false, false, []);
        }

        return new AttributeGroup([new Attribute($attrName, $args)]);
    }

    protected function generateEnumViewAttribute(): AttributeGroup
    {
        $attrName = new Name($this->getClassName(EnumView::class));
        $args = [];

        return new AttributeGroup([new Attribute($attrName, $args)]);
    }

    protected function generateArrayTypeAttribute(string $valueType): AttributeGroup
    {
        $attrName = new Name($this->getClassName(ArrayType::class));
        $args = [];
        if (\class_exists($valueType)) {
            $args[] = new Arg(new ClassConstFetch(new Name($this->getClassName($valueType)), new Identifier(
                'class'
            )), false, false, [], new Identifier('value'));
        } else {
            $args[] = new Arg(new String_($valueType), false, false, [], new Identifier('value'));
        }

        return new AttributeGroup([new NodeAttribute($attrName, $args)]);
    }
}
