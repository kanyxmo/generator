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

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\{Controller, DeleteMapping, GetMapping, PatchMapping, PostMapping, PutMapping, RequestMapping};
use Mine\Abstract\BaseController;
use Mine\Annotation\ApiOperation;
use Mine\Annotation\{Api, Auth, Operation, Permission};
use Mine\Devtool\Visitor\AbstractVisitor;
use Mine\Devtool\Visitor\GenOption;
use Mine\Devtool\Visitor\RewriteMethodRouteVisitor;
use Mine\DTO\Annotation\Header;
use Mine\DTO\Annotation\RequestBody;
use Mine\DTO\Annotation\RequestQuery;
use Mine\DTO\Annotation\Validation;
use Mine\Response\SuccessResponse;
use Hyperf\Stringable\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\NodeTraverser;
use PhpParser\{BuilderHelpers, Node, Node\Expr, Node\Scalar, Node\Scalar\MagicConst, Node\Stmt};

use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{RequestInterface, ResponseInterface};

use Throwable;

use function array_key_exists;

use function Hyperf\Support\value;

class ControllerGen extends AbstractGenerator
{
    protected ?string $server = null;

    protected ?string $prefix = null;

    protected ?string $title = '';

    protected bool $auth = true;

    protected bool $permission = true;

    protected array $relation = [];

    protected bool $noPrefix = false;

    protected function configure(): void
    {
        // if ($this->fs->exists($this->option->getFile())) {
        //     $this->fs->delete($this->option->getFile());
        // }
        if ($this->inheritance && (class_exists($this->inheritance) || interface_exists($this->inheritance))) {
            $this->option->setUse($this->inheritance)
                ->setProperty('instance', new Property(
                    Class_::MODIFIER_PROTECTED,
                    [new PropertyProperty('instance')],
                    [],
                    new Name($this->getClassName($this->inheritance)),
                    // new NullableType(
                    //     new Name($this->getClassName($this->inheritance)),
                    // ),
                    [new AttributeGroup([new Attribute(new Name($this->getClassName(Inject::class)))])],
                ));
        } else {
            $this->option->setProperty('instance', new Property(
                Class_::MODIFIER_PROTECTED,
                [new PropertyProperty('instance')],
            ));
            $this->option->removeUse($this->inheritance);
        }

        // 注入路由
        $target = $this->getIdentifier($this->name);
        $this->option->setUse(BaseController::class)
            ->setInheritance(BaseController::class)
            ->setUse(ApiOperation::class)
            ->setUse(GetMapping::class)
            ->setUse(PostMapping::class)
            ->setUse(PutMapping::class)
            ->setUse(DeleteMapping::class)
            ->setUse(PatchMapping::class)
            ->setUse(Inject::class)
            ->setUse(Controller::class)
            ->setUse(Validation::class)
            ->setUse(RequestBody::class)
            ->setUse(RequestQuery::class)
            ->setUse(SuccessResponse::class)
            ->setUse(Throwable::class)
            ->setPrefix($target)
            ->setTitle($this->title)
            ->setAttribute('Controller', new AttributeGroup([new Attribute(new Name('Controller'), value(function () use (
                $target
            ): array {
                $args = [];
                if ($this->server) {
                    $args[] = new Arg(new String_($this->server), false, false, [], new Identifier('server'));
                }
                $args[] = new Arg(new String_(Str::beforeLast(
                    Str::replace(':', '/', $target),
                    '/'
                )), false, false, [], new Identifier('prefix'));

                return $args;
            }))]))
            // ->setUse(Api::class)
            // ->setAttribute('Api', new AttributeGroup([new Attribute(new Name('Api'), [
            //     new Arg(new String_($this->title), false, false, [], new Identifier('tags')),
            //     new Arg(new String_($this->title), false, false, [], new Identifier('description')),
            // ])]))
            // ->setUse(Header::class)
            // ->setAttribute('Header', new AttributeGroup([new Attribute(new Name('Header'), [
            //     new Arg(new String_('Authorization'), false, false, [], new Identifier('name')),
            // ])]))
            ->setUse(Auth::class)
            ->setAttribute('Auth', new AttributeGroup([new Attribute(new Name('Auth'))]))
            ->setUse(Permission::class)
            ->setAttribute('Permission', new AttributeGroup([new Attribute(new Name('Permission'), [
                new Arg(new String_($target), false, false, [], new Identifier('value')),
                new Arg(new String_($this->title), false, false, [], new Identifier('title')),
                new Arg(new String_('self'), false, false, [], new Identifier('component')),
                new Arg(new LNumber(0), false, false, [], new Identifier('hidden')),
                new Arg(new String_('eos-icons:neural-network'), false, false, [], new Identifier('icon')),
                new Arg(new ClassConstFetch(new Name('Permission'), new Identifier(
                    'VIEW'
                )), false, false, [], new Identifier('type')),
                new Arg(new LNumber(1), false, false, [], new Identifier('status')),
                new Arg(new LNumber(1), false, false, [], new Identifier('sort')),
            ])]))
            ->setVisitors(
                new RewriteMethodRouteVisitor($this->option, $this->auth, $this->permission, $this->prefix),
                999
            )
            ->setVisitors(new class($this->option) extends AbstractVisitor {
                public function leaveNode(Node $node): int|Node
                {
                    // if ($node instanceof ClassMethod) {
                    //     if ($node->name->toString() == 'getList') {
                    //         $node->name = new Identifier('lsit');
                    //     }
                    //     if (in_array($node->name->toString(), ['recovery', 'toggle', 'change'])) {
                    //         return NodeTraverser::REMOVE_NODE;
                    //     }
                    //     if ($node->name->toString() == 'toggle') {
                    //         return NodeTraverser::REMOVE_NODE;
                    //     }
                    // }
                    // if ($node instanceof Param) {
                    //     if ($node->var->name == 'response') {
                    //         return NodeTraverser::REMOVE_NODE;
                    //     }
                    // }

                    return $node;
                }
            }, 99999);

        ! class_exists($this->option->getClass()) && $this->getMethodNodes();
        // $this->getMethodNodes();
    }

    protected function getMethodNodes(): void
    {
        $code = '
        <?php
        class Controller
        {
            /**
             * 列表
             */
            #[GetMapping(path: "basic/{recycle:0|1}")]
            public function list(
                #[Validation]  #[RequestBody] ListQuery $query,
                #[Validation]  #[RequestBody] Search $search,
                #[RequestQuery]  Page $page,
                #[RequestQuery]  BaseSort $sort,
                ItemResponse $response
            ): ListResponse {
                return $this->instance->list($query, $search, $page, $sort);
            }

            /**
             * 新增
             */
            #[PostMapping(path: "basic")]
            public function create(
                #[Validation] #[RequestBody]  CreateQuery $query,
                #[Validation] #[RequestBody]  CreateData $data,
            ): SuccessResponse
            {
                return $this->instance->create($query,$data);
            }

            /**
             * 更新
             */
            #[PostMapping(path: "basic/{id:\d+}")]
            public function update(
                #[Validation] #[RequestBody] UpdateQuery $query,
                #[Validation] #[RequestBody] UpdateData $data,
            ): SuccessResponse
            {
                return $this->instance->update($query,$data);
            }

            /**
             * 详情
             */
            #[GetMapping(path: "basic/{id:\d+}/{recycle:0|1}")]
            public function detail(
                #[Validation] #[RequestBody]  DetailQuery $query,
                ItemResponse $response
            ): DetailResponse {
                return $this->instance->detail($query, $response);
            }

            /**
             * 删除
             */
            #[DeleteMapping(path: "basic/{id:\d+}/{recycle:0|1}")]
            public function delete(
                #[Validation] #[RequestBody]  DeleteQuery $query
            ): SuccessResponse {
                return $this->instance->delete($query);
            }

            /**
             * 恢复
             */
            #[PatchMapping(path: "basic/{id:\d+}")]
            public function recovery(
                #[Validation] #[RequestBody] RecoveryQuery $query
            ): SuccessResponse {
                return $this->instance->recovery($query);
            }

            /**
             * 状态更改
             */
            #[PatchMapping(path: "basic/{field:\w+}/{id:\d+}")]
            public function change(
                #[Validation] #[RequestQuery] ChangeQuery $query
            ): SuccessResponse {
                return $this->instance->change($query);
            }

            /**
             * 更新指定字段值
             */
            #[PutMapping(path: "basic/{field:\w+}/{id:\d+}")]
            public function toggle(
                #[Validation] #[RequestBody] ToggleQuery $query
            ): SuccessResponse {
                return $this->instance->toggle($query);
            }
        }';
        $stmts = $this->parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($this->option, $this->relation) extends AbstractVisitor {
            public function __construct(
                GenOption $option,
                protected array $relation
            ) {
                parent::__construct($option);
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof String_) {
                    if (Str::contains($node->value, '%s')) {
                        return new String_(sprintf($node->value, $this->option->getTitle()));
                    }
                }
                if ($node instanceof Name) {
                    if (array_key_exists($node->toString(), $this->relation)) {
                        $this->option->setUse($this->relation[$node->toString()]);

                        return new Name($this->getClassName($this->relation[$node->toString()]));
                    }
                }
                // if($node instanceof \PhpParser\Node\Expr\New_){
                // var_dump($node);

                // }
                if ($node instanceof ClassMethod) {
                    $this->option->setMethod($node->name->toString(), $node);
                }
            }
        });
        $stmts = $traverser->traverse($stmts);
    }
}
