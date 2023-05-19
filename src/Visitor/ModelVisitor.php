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

use Carbon\Carbon;
use Hyperf\CodeParser\PhpDocReader;
use Hyperf\CodeParser\PhpParser;
use Hyperf\Contract\Castable;
use Hyperf\Contract\CastsAttributes;
use Hyperf\Contract\CastsInboundAttributes;
use Hyperf\Database\Commands\Ast\GenerateModelIDEVisitor;
use Hyperf\Database\Commands\ModelOption;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\Relations\HasManyThrough;
use Hyperf\Database\Model\Relations\HasOne;
use Hyperf\Database\Model\Relations\HasOneThrough;
use Hyperf\Database\Model\Relations\MorphMany;
use Hyperf\Database\Model\Relations\MorphOne;
use Hyperf\Database\Model\Relations\MorphTo;
use Hyperf\Database\Model\Relations\MorphToMany;
use Hyperf\Database\Model\Relations\Relation;
use Hyperf\Stringable\Str;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

use function Hyperf\Support\class_basename;

class ModelVisitor extends NodeVisitorAbstract
{
    protected Model $class;

    /**
     * @var Node\Stmt\ClassMethod[]
     */
    protected array $methods = [];

    protected array $properties = [];

    /**
     * @var array<string, class-string>
     */
    final public const RELATION_METHODS = [
        'hasMany' => HasMany::class,
        'hasManyThrough' => HasManyThrough::class,
        'hasOneThrough' => HasOneThrough::class,
        'belongsToMany' => BelongsToMany::class,
        'hasOne' => HasOne::class,
        'belongsTo' => BelongsTo::class,
        'morphOne' => MorphOne::class,
        'morphTo' => MorphTo::class,
        'morphMany' => MorphMany::class,
        'morphToMany' => MorphToMany::class,
        'morphedByMany' => MorphToMany::class,
    ];

    public function __construct(
        string $class,
        protected array $columns,
        protected ModelOption $option,
        protected ?array $uses = [],
        protected ?array $exclude = []
    ) {
        $this->class = new $class();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->methods = PhpParser::getInstance()->getAllMethodsFromStmts($nodes);
        sort($this->methods);

        $this->initPropertiesFromMethods();

        return null;
    }

    public function enterNode(Node $node): void
    {
        if ($node instanceof UseUse) {
            $alias = $node->name->getLast();
            if ($node->alias) {
                $alias = $node->alias->toString();
            }
            $this->uses($node->name->toString(), $alias);
        }
    }

    public function leaveNode(Node $node)
    {
        switch ($node) {
            case $node instanceof PropertyProperty:
                if ((string) $node->name === 'fillable' && $this->option->isRefreshFillable()) {
                    $node = $this->rewriteFillable($node);
                } elseif ((string) $node->name === 'casts') {
                    $node = $this->rewriteCasts($node);
                }

                return $node;
            case $node instanceof Class_:
                $node->setDocComment(new Doc($this->parse()));
                if ($inheritance = $this->option->getInheritance()) {
                    if (! $node->extends || $inheritance != $node->extends->toString()) {
                        $node->extends = new Name($inheritance);
                    }
                }

                return $node;
            case $node instanceof UseUse:
                $class = $node->name->toString();
                if (in_array($class, array_keys($this->uses))) {
                    unset($this->uses[$class]);
                }

                return $node;
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Namespace_) {
                continue;
            }

            foreach ($this->uses as $use => $alias) {
                array_unshift($namespace->stmts, new Use_([
                    new UseUse(new Name($use), $alias ? new Name($alias) : $alias),
                ]));
            }
        }

        return $nodes;
    }

    protected function rewriteFillable(PropertyProperty $node): PropertyProperty
    {
        $items = [];
        foreach ($this->columns as $column) {
            if (in_array($column['column_name'], $this->exclude)) {
                continue;
            }
            $items[] = new ArrayItem(new String_($column['column_name']));
        }

        $node->default = new Array_($items, [
            'kind' => Array_::KIND_SHORT,
        ]);

        return $node;
    }

    protected function rewriteCasts(PropertyProperty $node): PropertyProperty
    {
        $items = [];
        $keys = [];
        if ($node->default instanceof Array_) {
            $items = $node->default->items;
        }

        if ($this->option->isForceCasts()) {
            $items = [];
            $casts = $this->class->getCasts();
            assert($node->default instanceof Array_);
            foreach ($node->default?->items as $item) {
                assert($item->key instanceof String_);
                assert($item instanceof ArrayItem);
                $caster = $casts[$item->key->value] ?? null;
                if ($caster && $this->isCaster($caster)) {
                    if (class_exists($caster)) {
                        $this->uses($caster, null);
                        $item->value = new ClassConstFetch(new Name(class_basename($caster)), new Identifier('class'));
                    }
                    $items[] = $item;
                }
            }
        }

        foreach ($items as $item) {
            $keys[] = $item->key->value;
        }

        foreach ($this->columns as $column) {
            $name = $column['column_name'];
            $type = $column['cast'] ?? null;
            if (in_array($name, $keys)) {
                continue;
            }

            if ($type || $type = $this->formatDatabaseType($column['data_type'])) {
                if ($type == 'datetime') {
                    $type = $this->formatDatabaseType($type);
                }
                if ($this->isCaster($type)) {
                    $this->uses($type, null);
                    $value = new ClassConstFetch(new Name(class_basename($type)), new Identifier('class'));
                } else {
                    $value = new String_($type);
                }
                $items[] = new ArrayItem($value, new String_($name));
            }
        }

        $node->default = new Array_($items, [
            'kind' => Array_::KIND_SHORT,
        ]);

        return $node;
    }

    /**
     * @param object|string $caster
     */
    protected function isCaster($caster): bool
    {
        return is_subclass_of($caster, CastsAttributes::class)
            || is_subclass_of($caster, Castable::class)
            || is_subclass_of($caster, CastsInboundAttributes::class);
    }

    protected function parse(): string
    {
        $doc = '/**' . PHP_EOL;
        $doc = $this->parseProperty($doc);
        if ($this->option->isWithIde()) {
            $doc .= ' * @mixin \\' . GenerateModelIDEVisitor::toIDEClass($this->class::class) . PHP_EOL;
        }

        $doc .= ' */';

        return $doc;
    }

    protected function parseProperty(string $doc): string
    {
        foreach ($this->columns as $column) {
            [$name, $type, $comment] = $this->getProperty($column);
            if (array_key_exists($name, $this->properties)) {
                if (! empty($comment)) {
                    $this->properties[$name]['comment'] = $comment;
                }

                continue;
            }
            if (class_exists($type)) {
                $this->uses($type, null);
                $type = class_basename($type);
            }
            $doc .= sprintf(' * @property %s $%s %s', $type, $name, $comment) . PHP_EOL;
        }

        foreach ($this->properties as $name => $property) {
            if (class_exists($property['type'])) {
                $this->uses($property['type'], null);
                $property['type'] = class_basename($property['type']);
            }
            $comment = $property['comment'] ?? '';
            if ($property['read'] && $property['write']) {
                $doc .= sprintf(' * @property %s $%s %s', $property['type'], $name, $comment) . PHP_EOL;

                continue;
            }

            if ($property['read']) {
                $doc .= sprintf(' * @property-read %s $%s %s', $property['type'], $name, $comment) . PHP_EOL;

                continue;
            }

            if ($property['write']) {
                $doc .= sprintf(' * @property-write %s $%s %s', $property['type'], $name, $comment) . PHP_EOL;

                continue;
            }
        }

        return $doc;
    }

    protected function initPropertiesFromMethods(): void
    {
        $reflection = new ReflectionClass($this->class::class);
        $casts = $this->class->getCasts();

        foreach ($this->methods as $methodStmt) {
            $methodName = $methodStmt->name->name;
            $method = $reflection->getMethod($methodName);
            if (Str::startsWith($method->getName(), 'get') && Str::endsWith($method->getName(), 'Attribute')) {
                // Magic get<name>Attribute
                $name = Str::snake(substr($method->getName(), 3, -9));
                if (! empty($name)) {
                    $type = PhpDocReader::getInstance()->getReturnType($method, true);
                    $this->setProperty($name, $type, true, null, '', false, 1);
                }

                continue;
            }

            if (Str::startsWith($method->getName(), 'set') && Str::endsWith($method->getName(), 'Attribute')) {
                // Magic set<name>Attribute
                $name = Str::snake(substr($method->getName(), 3, -9));
                if (! empty($name)) {
                    $this->setProperty($name, null, null, true, '', false, 1);
                }

                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            $return = end($methodStmt->stmts);
            if ($return instanceof Return_) {
                $expr = $return->expr;
                if (
                    $expr instanceof MethodCall
                    && $expr->name instanceof Identifier
                    && is_string($expr->name->name)
                ) {
                    $loop = 0;
                    while ($expr->var instanceof MethodCall) {
                        if ($loop > 32) {
                            throw new RuntimeException('max loop reached!');
                        }

                        ++$loop;
                        $expr = $expr->var;
                    }

                    $name = $this->getMethodRelationName($method) ?? $expr->name->name;
                    if (array_key_exists($name, self::RELATION_METHODS)) {
                        if ($name === 'morphTo') {
                            // Model isn't specified because relation is polymorphic
                            $this->setProperty($method->getName(), ['\\' . Model::class], true);
                        } elseif (isset($expr->args[0]) && $expr->args[0]->value instanceof ClassConstFetch) {
                            $ccf = $expr->args[0]->value;
                            assert($ccf instanceof ClassConstFetch);
                            $related = $ccf->class->toCodeString();
                            if (str_contains($name, 'Many')) {
                                // Collection or array of models (because Collection is Arrayable)
                                $this->setProperty(
                                    $method->getName(),
                                    [$this->getCollectionClass($related), $related . '[]'],
                                    true
                                );
                            } else {
                                // Single model is returned
                                $this->setProperty($method->getName(), [$related], true);
                            }
                        }
                    }
                }
            }
        }

        // The custom caster.
        foreach ($casts as $key => $caster) {
            if (is_subclass_of($caster, Castable::class)) {
                $caster = $caster::castUsing();
            }

            if (is_subclass_of($caster, CastsAttributes::class)) {
                $ref = new ReflectionClass($caster);
                $method = $ref->getMethod('get');
                if (($type = $method->getReturnType()) !== null) {
                    // Get return type which defined in `CastsAttributes::get()`.
                    if ($type == 'static' || $type == 'self') {
                        $this->setProperty($key, ['\\' . ltrim((string) $caster, '\\')], true, true);
                    } else {
                        $this->setProperty($key, ['\\' . ltrim((string) $type->getName(), '\\')], true, true);
                    }
                }
            }
        }
    }

    protected function getMethodRelationName(ReflectionMethod $method): ?string
    {
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            $array = explode('\\', $returnType->getName());

            return Str::camel(array_pop($array));
        }

        return null;
    }

    protected function setProperty(
        string $name,
        array $type = null,
        bool $read = null,
        bool $write = null,
        string $comment = '',
        bool $nullable = false,
        int $priority = 0
    ): void {
        if (! isset($this->properties[$name])) {
            $this->properties[$name] = [];
            $this->properties[$name]['type'] = 'mixed';
            $this->properties[$name]['read'] = false;
            $this->properties[$name]['write'] = false;
            $this->properties[$name]['comment'] = $comment;
            $this->properties[$name]['priority'] = 0;
        }

        if ($this->properties[$name]['priority'] > $priority) {
            return;
        }

        if ($type !== null) {
            if ($nullable) {
                $type[] = 'null';
            }

            $this->properties[$name]['type'] = implode('|', array_unique($type));
        }

        if ($read !== null) {
            $this->properties[$name]['read'] = $read;
        }

        if ($write !== null) {
            $this->properties[$name]['write'] = $write;
        }

        $this->properties[$name]['priority'] = $priority;
    }

    protected function getProperty($column): array
    {
        $name = $this->option->isCamelCase() ? Str::camel($column['column_name']) : $column['column_name'];

        $type = $this->formatPropertyType($column['data_type'], $column['cast'] ?? null);

        $comment = $this->option->isWithComments() ? $column['column_comment'] ?? '' : '';

        return [$name, $type, $comment];
    }

    protected function formatDatabaseType(string $type): ?string
    {
        return match ($type) {
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint','enum' => 'integer',
            'decimal' => 'decimal:2',
            'float', 'double', 'real' => 'float',
            'bool', 'boolean' => 'boolean',
            'datetime', 'timestamp' => 'datetime:Y-m-d H:i:s',
            'date' => 'datetime:Y-m-d',
            'time' => 'datetime:H:i:s',
            'year' => 'datetime:Y',
            'json' => 'array',
            default => null,
        };
    }

    protected function formatPropertyType(string $type, ?string $cast): ?string
    {
        if ($this->isCaster($cast)) {
            $cast = '\\' . $cast;
        } else {
            $cast = $this->formatDatabaseType($type) ?? 'string';
        }

        if (Str::startsWith($cast, 'datetime')) {
            $cast = '\\' . Carbon::class;
        }

        if (Str::startsWith($cast, 'decimal')) {
            $cast = 'string';
        }

        return match ($cast) {
            'integer' => 'int',
            'decimal' => 'string',
            'json' => 'array',
            default => $cast,
        };
    }

    protected function getCollectionClass($className): string
    {
        // Return something in the very very unlikely scenario the model doesn't
        // have a newCollection() method.
        if (! method_exists($className, 'newCollection')) {
            return '\\' . Collection::class;
        }

        /** @var Model $model */
        $model = new $className();

        return '\\' . $model->newCollection()::class;
    }

    private function uses(string $class, ?string $alias = null): void
    {
        $this->uses[$class] = $alias;
    }
}
