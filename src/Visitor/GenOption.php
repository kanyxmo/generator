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

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\TraitUse;

class GenOption
{
    /**
     * @var int
     */
    final public const TYPE_CLASS = 0;

    /**
     * @var int
     */
    final public const TYPE_INTERFACE = 1;

    /**
     * @var int
     */
    final public const TYPE_TRAIT = 2;

    /**
     * @var int
     */
    final public const TYPE_ENUM = 3;

    public function __construct(
        protected string $namespace,
        protected string $class,
        protected string $file,
        protected int $type = self::TYPE_CLASS,
        protected bool $reset = false,
        protected ?array $uses = [],
        protected ?array $methods = [],
        protected ?array $propertys = [],
        protected ?array $visitors = [],
        protected ?array $inheritance = [],
        protected ?array $implements = [],
        protected ?array $attributes = [],
        protected ?string $prefix = null,
        protected ?string $title = null,
        protected ?array $traitUses = [],
    ) {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): static
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        switch ($type) {
            case self::TYPE_CLASS:
                $this->type = $type;

                break;
            case self::TYPE_INTERFACE:
                $this->type = $type;

                break;
            case self::TYPE_TRAIT:
                $this->type = $type;

                break;
            case self::TYPE_ENUM:
                $this->type = $type;

                break;
            default:
                break;
        }

        return $this;
    }

    public function getReset(): bool
    {
        return $this->reset;
    }

    public function setReset(bool $reset): static
    {
        $this->reset = $reset;

        return $this;
    }

    public function getUses(): array
    {
        ksort($this->uses);

        return $this->uses;
    }

    public function setUses(array $uses): static
    {
        $this->uses = array_unique($uses);
        ksort($this->uses);

        return $this;
    }

    public function getUseAlias(string $class): string|null
    {
        return $this->uses[$class] ?? null;
    }

    public function setUse(string $class, ?string $alias = null): static
    {
        $this->uses[$class] = $alias;

        return $this;
    }

    public function removeUse(string $class): static
    {
        unset($this->uses[$class]);

        return $this;
    }

    public function hasUse(string $class): bool
    {
        return isset($this->uses[$class]);
    }

    public function getInheritance(): ?string
    {
        return $this->inheritance[0] ?? null;
    }

    public function getInheritanceAlias(): ?string
    {
        return $this->inheritance[1] ?? null;
    }

    public function setInheritance(string $class, ?string $alias = null): static
    {
        $this->inheritance = [$class, $alias];

        return $this;
    }

    public function getImplements(): ?array
    {
        return $this->implements;
    }

    public function setImplements(string $class, ?string $alias = null): static
    {
        $this->implements[$class] = $alias;

        return $this;
    }

    public function removeImplements(string $class): static
    {
        unset($this->implements[$class]);

        return $this;
    }

    public function hasImplements(string $class): bool
    {
        return isset($this->implements[$class]);
    }

    public function getMethods(): ?array
    {
        return $this->methods;
    }

    public function getMethodByValues(): ?array
    {
        return array_values($this->methods);
    }

    public function getMethodByKeys(): ?array
    {
        return array_keys($this->methods);
    }

    public function setMethods(array $methods): static
    {
        foreach ($methods as $method => $node) {
            if (is_string($method) && ($node instanceof ClassMethod || $node instanceof ClassConst)) {
                $this->methods[$method] = $node;
            }
        }

        return $this;
    }

    public function clearMethods(): static
    {
        $this->methods = [];

        return $this;
    }

    public function getMethod(string $method): ClassMethod|ClassConst|null
    {
        return $this->methods[$method] ?? null;
    }

    public function setMethod(string $method, ClassMethod|ClassConst $node): static
    {
        $this->methods[$method] = $node;

        return $this;
    }

    public function removeMethod(string $method): static
    {
        unset($this->methods[$method]);

        return $this;
    }

    public function hasMethod(string $method): bool
    {
        return isset($this->methods[$method]);
    }

    public function getPropertys(): ?array
    {
        return $this->propertys;
    }

    public function getPropertyByValues(): ?array
    {
        return array_values($this->propertys);
    }

    public function getPropertyByKeys(): ?array
    {
        return array_keys($this->propertys);
    }

    public function setPropertys(array $propertys): static
    {
        foreach ($propertys as $property => $node) {
            if (is_string($property) && ($node instanceof Property || $node instanceof PropertyProperty)) {
                $this->propertys[$property] = $node;
            }
        }

        return $this;
    }

    public function getProperty(string $property): Property|PropertyProperty|null
    {
        return $this->propertys[$property] ?? null;
    }

    public function setProperty(string $property, Property|PropertyProperty $node): static
    {
        $this->propertys[$property] = $node;

        return $this;
    }

    public function removeProperty(string $property): static
    {
        unset($this->propertys[$property]);

        return $this;
    }

    public function hasProperty(string $property): bool
    {
        return isset($this->propertys[$property]);
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function getAttributeByValues(): ?array
    {
        return array_values($this->attributes);
    }

    public function getAttributeByKeys(): ?array
    {
        return array_keys($this->attributes);
    }

    public function setAttribute(string $attribute, AttributeGroup|Attribute $node): static
    {
        $this->attributes[$attribute] = $node;

        return $this;
    }

    public function removeAttribute(string $attribute): static
    {
        unset($this->attributes[$attribute]);

        return $this;
    }

    public function hasAttribute(string $attribute): bool
    {
        return isset($this->attributes[$attribute]);
    }

    /**
     * Get the value of traitUses.
     */
    public function getTraitUses(): ?array
    {
        return $this->traitUses;
    }

    /**
     * Set the value of traitUses.
     */
    public function setTraitUses(TraitUse $traitUses): self
    {
        $this->traitUses[] = $traitUses;

        return $this;
    }

    public function getVisitors(): ?array
    {
        ksort($this->visitors);

        return $this->visitors;
    }

    public function setVisitors($visitors, int $priority = 0): static
    {
        if ($priority == 0 || ! is_int($priority)) {
            $priority = count((array) $this->visitors) + 1;
            while (isset($this->visitors[$priority])) {
                ++$priority;
            }
        }

        $this->visitors[$priority] = $visitors;
        ksort($this->visitors);

        return $this;
    }

    public function getPrefix(): string|null
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Get the value of title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set the value of title.
     */
    public function setTitle(mixed $title): self
    {
        $this->title = $title;

        return $this;
    }
}
