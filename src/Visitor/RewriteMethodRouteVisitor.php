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

use Mine\Annotation\Auth;
use Mine\Annotation\Permission;
use Hyperf\Stringable\Str;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;

use function Hyperf\Support\value;

class RewriteMethodRouteVisitor extends AbstractVisitor
{
    private bool $adderClassPermission = true;

    private bool $adderClassAuth = true;

    private bool $adder = true;

    private bool $adderAuth = true;

    private array  $attrMethods = [
        'list' => [
            'value' => 'list',
            'title' => '%s列表',
            'component' => 'list',
            'hidden' => 1,
            'icon' => 'line-md:list-3',
            'type' => ['VIEW', 'API'],
            'status' => 1,
            'sort' => 1,
            'singleLayout' => 'blank',
        ],
        'detail' => [
            'value' => 'detail',
            'title' => '%s详情',
            'component' => 'detail',
            'hidden' => 1,
            'icon' => 'line-md:text-box',
            'type' => ['VIEW', 'BUTTON', 'API'],
            'status' => 1,
            'sort' => 2,
            'singleLayout' => 'blank',
        ],
        'create' => [
            'value' => 'create',
            'title' => '新增%s',
            'component' => 'from',
            'hidden' => 1,
            'icon' => 'line-md:document-add',
            'type' => ['VIEW', 'BUTTON', 'API'],
            'status' => 1,
            'sort' => 3,
            'singleLayout' => 'blank',
        ],
        'update' => [
            'value' => 'update',
            'title' => '更新%s',
            'component' => 'from',
            'hidden' => 1,
            'icon' => 'line-md:edit',
            'type' => ['VIEW', 'BUTTON', 'API'],
            'status' => 1,
            'sort' => 4,
            'singleLayout' => 'blank',
        ],
        'delete' => [
            'value' => 'delete',
            'title' => '删除%s',
            'component' => 'blank',
            'hidden' => 1,
            'icon' => 'line-md:document-remove',
            'type' => ['BUTTON', 'API'],
            'status' => 1,
            'sort' => 5,
        ],
        'recovery' => [
            'value' => 'recoveryt',
            'title' => '恢复%s',
            'component' => 'blank',
            'hidden' => 1,
            'icon' => 'flat-color-icons:data-recovery',
            'type' => ['BUTTON', 'API'],
            'status' => 1,
            'sort' => 6,
        ],
        'change' => [
            'value' => 'change',
            'title' => '修改%s',
            'component' => 'blank',
            'hidden' => 1,
            'icon' => 'ic:round-published-with-changes',
            'type' => ['BUTTON', 'API'],
            'status' => 1,
            'sort' => 7,
        ],
        'toggle' => [
            'value' => 'toggle',
            'title' => '调整%s',
            'component' => 'blank',
            'hidden' => 1,
            'icon' => 'carbon:3d-mpr-toggle',
            'type' => ['API'],
            'status' => 1,
            'sort' => 8,
        ],
    ];

    public function __construct(
        protected GenOption $option,
        protected bool $auth = true,
        protected bool $permission = true,
        protected ?string $prefix = null,
    ) {
        parent::__construct($option);
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Attribute) {
            $parent = $node->getAttribute('parent');
            $grandpa = $parent->getAttribute('parent');
            if ($node->name instanceof Identifier) {
                $name = $node->name->toString();
            } elseif ($node->name instanceof Name) {
                $name = $node->name->getLast();
            }
            if ($grandpa instanceof ClassMethod && $name == 'Permission') {
                $this->adder = false;
            }
            if ($grandpa instanceof ClassMethod && $name == 'Auth') {
                $this->adderAuth = false;
            }
            if ($grandpa instanceof Class_ && $name == 'Permission') {
                $this->adderClassPermission = false;
            }
            if ($grandpa instanceof Class_ && $name == 'Auth') {
                $this->adderClassAuth = false;
            }
        }

        return $node;
    }

    public function leaveNode(Node $node)
    {
        $class = null;
        $node = parent::handleLeaveNode($node);
        if ($node instanceof Class_) {
            if ($this->auth && $this->adderClassAuth) {
                $this->option->setUse(Auth::class);
                $node->attrGroups[] = new AttributeGroup([new Attribute(new Name('Auth'))]);
            }
            if ($this->permission && $this->adderClassPermission) {
                $this->option->setUse(Permission::class);
                $node->attrGroups[] = new AttributeGroup([new Attribute(new Name('Permission'), [
                    new Arg(new String_(Str::replace(
                        '::',
                        ':',
                        $this->option->getPrefix()
                    )), false, false, [], new Identifier('value')),
                    new Arg(new String_($this->option->getTitle()), false, false, [], new Identifier('title')),
                    new Arg(new String_('page'), false, false, [], new Identifier('component')),
                    new Arg(new LNumber(0), false, false, [], new Identifier('hidden')),
                    new Arg(new String_('eos-icons:neural-network'), false, false, [], new Identifier('icon')),
                    new Arg($this->genAttrTypeArg(['VIEW', 'API']), false, false, [], new Identifier('type')),
                    new Arg(new LNumber(1), false, false, [], new Identifier('status')),
                    new Arg(new LNumber(1), false, false, [], new Identifier('sort')),
                ])]);
            }

            return $node;
        }
        if ($node instanceof ClassMethod) {
            if ($this->auth && $this->adderAuth) {
                $node->attrGroups[] = new AttributeGroup([new Attribute(new Name('Auth'))]);
            }
            if ($this->permission && $this->adder && $methodAttr = $this->genMethodAttr($node->name->toString())) {
                $node->attrGroups[] = $methodAttr;
            }

            return $node;
        }
        if ((! $this->permission || ! $this->auth) && $node instanceof AttributeGroup) {
            $parent = $node->getAttribute('parent');
            $authConfirm = false;
            $permissionCconfirm = false;
            foreach ($node->attrs as $next) {
                if (! ($next instanceof Attribute)) {
                    continue;
                }
                if ($next->name instanceof Identifier) {
                    $name = $next->name->toString();
                } elseif ($next->name instanceof Name) {
                    $name = $next->name->getLast();
                }
                if ($name == 'Auth') {
                    $authConfirm = true;
                } elseif ($name == 'Permission') {
                    $permissionCconfirm = true;
                }
            }
            if ($parent instanceof Class_ && ! $this->permission && ! $this->adderClassPermission) {
                if ($permissionCconfirm) {
                    $this->adderClassPermission = true;
                    $permissionCconfirm = false;

                    return NodeTraverser::REMOVE_NODE;
                }
            }
            if ($parent instanceof Class_ && ! $this->auth && ! $this->adderClassAuth) {
                if ($authConfirm) {
                    $this->adderClassAuth = true;
                    $authConfirm = false;

                    return NodeTraverser::REMOVE_NODE;
                }
            }
            if ($parent instanceof ClassMethod && ! $this->permission && ! $this->adder) {
                if ($permissionCconfirm) {
                    $this->adder = true;
                    $permissionCconfirm = false;

                    return NodeTraverser::REMOVE_NODE;
                }
            }
            if ($parent instanceof ClassMethod && ! $this->auth && ! $this->adderAuth) {
                if ($authConfirm) {
                    $this->adderAuth = true;
                    $authConfirm = false;

                    return NodeTraverser::REMOVE_NODE;
                }
            }
        }
        if ($node instanceof Arg) {
            $parent = $node->getAttribute('parent');
            $grandpa = $parent->getAttribute('parent');
            $great = $grandpa->getAttribute('parent');
            $identify = Str::replace('::', ':', $this->option->getPrefix());
            if ($parent->name instanceof Identifier) {
                $class = $parent->name->toString();
            } elseif ($parent->name instanceof Name) {
                $class = $parent->name->getLast();
            }
            if ($great instanceof Class_ && $class == 'Permission') {
                if ($node->name == 'value') {
                    $node->value = new String_($identify);
                }
                if ($node->name == 'title') {
                    $node->value = new String_($this->option->getTitle());
                }
                if ($node->name == 'type') {
                    $node->value = $this->genAttrTypeArg(['VIEW', 'API']);
                }
            }
            if ($great instanceof ClassMethod && $class == 'Permission') {
                if ($great->name instanceof Identifier) {
                    $method = $great->name->toString();
                } elseif ($great->name instanceof Name) {
                    $method = $great->name->getLast();
                }
                $methodAttr = $this->attrMethods[$method];
                if ($methodAttr && $node->name == 'value') {
                    $node->value = new String_(Str::start(":{$methodAttr['value']}", $this->option->getPrefix()));
                }
                if ($methodAttr && $node->name == 'title') {
                    $node->value = new String_(sprintf($methodAttr['title'], $this->option->getTitle()));
                }
                if (isset($methodAttr['type']) && $node->name == 'type') {
                    $node->value = $this->genAttrTypeArg($methodAttr['type']);
                }
                if (isset($methodAttr['icon']) && $node->name == 'icon') {
                    $node->value = new String_($methodAttr['icon']);
                }
                if (isset($methodAttr['component']) && $node->name == 'component' && $methodAttr['component'] && in_array(
                    $methodAttr['component'],
                    ['basic', 'blank', 'multi', 'self', 'page']
                )) {
                    $node->value = new String_($methodAttr['component']);
                } elseif (isset($methodAttr['component']) && $node->name == 'component' && $methodAttr['component']) {
                    $component = Str::studly(
                        Str::replace(':', '_', Str::start(":{$methodAttr['component']}", $this->option->getPrefix()))
                    );
                    $node->value = new String_($component);
                }
                if (isset($methodAttr['singleLayout']) && $node->name == 'singleLayout' && $methodAttr['singleLayout']) {
                    $node->value = new String_($methodAttr['singleLayout']);
                }
            }

            if ($great instanceof Class_ && $class == 'Controller' && $node->name == 'prefix' && $node->value instanceof String_) {
                $node->value = new String_(Str::beforeLast(Str::replace(':', '/', $identify), '/'));
            }
            $mappings = ['GetMapping', 'DeleteMapping', 'GetMapping', 'PatchMapping', 'PostMapping', 'PutMapping'];
            if ($great instanceof ClassMethod && in_array(
                $class,
                $mappings
            ) && $node->name == 'path' && $node->value instanceof String_) {
                $after = Str::afterLast($identify, ':');
                $last = Str::after($node->value->value, '/');
                if (Str::contains($node->value->value, '/')) {
                    $value = new String_("{$after}/{$last}");
                } else {
                    $value = new String_("{$after}");
                }
                $node->value = $value;
            }

            return $node;
        }

        return $node;
    }

    protected function genMethodAttr(string $method): ?AttributeGroup
    {
        $methodAttr = $this->attrMethods[$method] ?? null;
        if ($methodAttr) {
            return new AttributeGroup([new Attribute(new Name('Permission'), [
                new Arg(new String_(Str::start(
                    ":{$methodAttr['value']}",
                    $this->option->getPrefix()
                )), false, false, [], new Identifier('value')),
                new Arg(new String_(sprintf(
                    $methodAttr['title'],
                    $this->option->getTitle()
                )), false, false, [], new Identifier('title')),
                new Arg(new LNumber($methodAttr['hidden']), false, false, [], new Identifier('hidden')),
                new Arg(new String_($methodAttr['icon']), false, false, [], new Identifier('icon')),
                new Arg($this->genAttrTypeArg($methodAttr['type']), false, false, [], new Identifier('type')),
                new Arg(new LNumber($methodAttr['status']), false, false, [], new Identifier('status')),
                new Arg(new LNumber($methodAttr['sort']), false, false, [], new Identifier('sort')),
                ...value(function () use ($methodAttr): array {
                    $arg = [];
                    if (isset($methodAttr['component']) && $methodAttr['component'] && in_array(
                        $methodAttr['component'],
                        ['basic', 'blank', 'multi', 'self', 'page']
                    )) {
                        $arg[] = new Arg(new String_($methodAttr['component']), false, false, [], new Identifier(
                            'component'
                        ));
                    } elseif (isset($methodAttr['component']) && $methodAttr['component']) {
                        $component = Str::studly(
                            Str::replace(':', '_', Str::start(
                                ":{$methodAttr['component']}",
                                $this->option->getPrefix()
                            ))
                        );
                        $arg[] = new Arg(new String_($component), false, false, [], new Identifier('component'));
                    }
                    if (isset($methodAttr['singleLayout']) && $methodAttr['singleLayout']) {
                        $arg[] = new Arg(new String_($methodAttr['singleLayout']), false, false, [], new Identifier(
                            'singleLayout'
                        ));
                    }

                    return $arg;
                }),

            ])]);
        }

        return null;
    }

    protected function genAttrTypeArg(array $type): Expr
    {
        if (count($type) === 1) {
            return new ClassConstFetch(new Name('Permission'), new Identifier($type[0]));
        }
        if (count($type) === 2) {
            return new BitwiseOr(
                new ClassConstFetch(new Name('Permission'), new Identifier($type[0])),
                new ClassConstFetch(new Name('Permission'), new Identifier($type[1])),
            );
        }
        if (count($type) === 3) {
            return new BitwiseOr(
                new BitwiseOr(
                    new ClassConstFetch(new Name('Permission'), new Identifier($type[0])),
                    new ClassConstFetch(new Name('Permission'), new Identifier($type[1])),
                ),
                new ClassConstFetch(new Name('Permission'), new Identifier($type[2])),
            );
        }
        if (count($type) === 4) {
            return new BitwiseOr(
                new BitwiseOr(
                    new BitwiseOr(
                        new ClassConstFetch(new Name('Permission'), new Identifier($type[0])),
                        new ClassConstFetch(new Name('Permission'), new Identifier($type[1])),
                    ),
                    new ClassConstFetch(new Name('Permission'), new Identifier($type[2])),
                ),
                new ClassConstFetch(new Name('Permission'), new Identifier($type[3])),
            );
        }

        return new ClassConstFetch(new Name('Permission'), new Identifier($type[0]));
    }
}
