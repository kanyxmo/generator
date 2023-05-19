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
use Mine\Annotation\EnumMessage;
use Hyperf\Stringable\Str;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Const_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Property;

use PhpParser\Node\Stmt\Trait_;

use Throwable;

use function array_values;

class PropertyConstVisitor extends AbstractVisitor
{
    protected string $class;

    public function __construct(
        protected GenOption $option,
        protected array $coust_args
    ) {
        parent::__construct($option);
    }

  public function enterNode(Node $node): null|int|Node
  {
      try {
          switch ($node) {
              case $node instanceof Class_:
                  $node->setDocComment(new Doc($this->generateDocAttribute()));
                  //     foreach ($node->stmts as $key => &$method) {
                  //         if ($method instanceof ClassConst) {
                  //             foreach ($method->consts as $const) {
                  //                 $this->rewriteConst($const);
                  //             }
                  //         }

                  //         if ($method instanceof ClassMethod) {
                  //             $method = $this->rewriteMethod($method);
                  //         }

                  //         if ($method instanceof Property) {
                  //             $method = $this->rewriteProperty($method);
                  //         }

                  //         if ($method instanceof TraitUse) {
                  //             if ($this->rewriteTraits($method)) {
                  //                 unset($node->stmts[$key]);
                  //             }
                  //         }
                  //     }

                  //     if (isset($node->implements)) {
                  //         foreach ($node->implements as $implement) {
                  //             $this->rewriteInterface($implement);
                  //         }
                  //     }

                  //     array_push($node->stmts, ...array_values($this->option->getMethods()));
                  //     array_unshift($node->stmts, ...$this->option->getPropertyByValues() ?? []);
                  //     array_unshift($node->stmts, ...$this->option->getTraitUses() ?? []);

                  return $node;
          }

          return $node;
      } catch (Exception $exception) {
          throw $exception;
      } catch (Throwable  $throwable) {
          throw $throwable;
      }
  }

    public function leaveNode(Node $node): int|Node|null
    {
        $node = $this->handleLeaveNode($node);
        switch ($node) {
            case $node instanceof Class_:
            case $node instanceof Trait_:
                $node->setDocComment(new Doc(PHP_EOL . $this->generateDocAttribute()));
                $node->stmts = $this->addProperties();

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
        foreach ($this->coust_args as $column) {
            $property = new ClassConst(
                [new Const_(Str::upper($column['column_name']), new LNumber((int) $column['default']))],
                Class_::MODIFIER_PUBLIC,
            );

            $property->attrGroups[] = $this->generateEnumMessageAttribute($column['column_comment']);
            $properties[] = $property;
        }

        return $properties;
    }

    protected function generateDocAttribute(): string
    {
        $doc = '/**' . PHP_EOL;
        $doc .= '*' . PHP_EOL;
        foreach ($this->coust_args as $column) {
            $doc .= sprintf(
                ' * @method static %s %s()',
                $this->getClassName($this->option->getClass()),
                Str::upper($column['column_name'])
            ) . PHP_EOL;
        }
        $doc .= ' */';

        return $doc;
    }

    protected function generateEnumMessageAttribute(string $name): AttributeGroup
    {
        $attrName = new Name($this->getClassName(EnumMessage::class));
        $args = [];
        $args[] = new Arg(new String_($name), false, false, []);

        return new AttributeGroup([new Attribute($attrName, $args)]);
    }
}
