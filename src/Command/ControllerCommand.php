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

namespace Mine\Devtool\Command;

use Hyperf\Collection\Arr;
use Hyperf\Command\Annotation\Command;
use Hyperf\HttpServer\Annotation\{Controller, DeleteMapping, GetMapping, PostMapping, PutMapping, RequestMapping};
use Hyperf\Stringable\Str;
use Mine\Annotation\{Auth, OperationLog, Permission};
use Mine\DTO\Annotation\ArrayType;
use Mine\Devtool\Generator\AttributesGen;
use Mine\Devtool\Generator\ConstantGen;
use Mine\Devtool\Generator\ContractGen;
use Mine\Devtool\Generator\ControllerGen;
use Mine\Devtool\Generator\LogicGen;
use Mine\Devtool\Generator\ModelGen;
use Mine\Devtool\Generator\RequestGen;
use Mine\Devtool\Generator\ResponseGen;
use Mine\Request\BaseQuery;
use Mine\Request\BaseRequest;
use Mine\Request\BaseSearch;
use Mine\Request\BaseSort;
use Mine\Request\Page;
use Mine\Response\BaseListResponse;
use Mine\Response\BaseResponse;
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Throwable;
use function Hyperf\Support\env;

#[Command]
class ControllerCommand extends GeneratorCommand
{
    protected ?string $name = 'gen:controller';

    protected array $relation = [
        'Page' => Page::class,
        'BaseSort' => BaseSort::class,
    ];

    protected string $title = '';

    protected string $genName = '';

    public function handle()
    {
        try {
            $errorEnum = [
                [
                    'column_key' => '',
                    'column_name' => 'CREATE_ERROR',
                    'data_type' => 'int',
                    'column_comment' => '创建%s失败！',
                    'extra' => '',
                    'column_type' => 'string',
                    'cast' => null,
                    'default' => 01,
                ],
                [
                    'column_key' => '',
                    'column_name' => 'UPDATE_ERROR',
                    'data_type' => 'int',
                    'column_comment' => '更新%s失败！',
                    'extra' => '',
                    'column_type' => 'string',
                    'cast' => null,
                    'default' => 02,
                ], [
                    'column_key' => '',
                    'column_name' => 'DELETE_ERROR',
                    'data_type' => 'int',
                    'column_comment' => '删除%s失败！',
                    'extra' => '',
                    'column_type' => 'string',
                    'cast' => null,
                    'default' => 03,
                ], [
                    'column_key' => '',
                    'column_name' => 'NOT_FOUND',
                    'data_type' => 'int',
                    'column_comment' => '%s不存在，请重试！',
                    'extra' => '',
                    'column_type' => 'string',
                    'cast' => null,
                    'default' => 04,
                ], [
                    'column_key' => '',
                    'column_name' => 'EXISTS',
                    'data_type' => 'int',
                    'column_comment' => '%s数据已被存在！',
                    'extra' => '',
                    'column_type' => 'string',
                    'cast' => null,
                    'default' => 05,
                ],
            ];
            $before = 'Appliction';
            if ($server = $this->getOptionInput('prefix', null)) {
                $before .= '\\' . Str::studly($server);
            }
            $before = '';
            $variable = $this->getOptionInput('name');
            if (! $variable) {
                $finders = Finder::create()->ignoreUnreadableDirs()->in(
                    (new ContractGen($this->getAppInput(), ''))->getApp('fullPath')
                )->files()
                    ->depth('<= 1')
                    ->name('*.php')
                    ->getIterator();
                foreach ($finders as $file) {
                    $name = Str::replaceLast('Interface', '', $file->getFilenameWithoutExtension());
                    $this->genName = $name;
                    $model = (new ModelGen($this->getAppInput(), $name));
                    $columns = $model->getColumns();
                    $this->title = $model->getTitle();
                    $this->genRequest($name, $before, $columns);
                    $this->genResponse($name, $before, $columns);
                    $enumAargv = $this->handleEnum($errorEnum, $this->title);
                    $this->generate(ConstantGen::class, $name, $before, 'Error', argv: $enumAargv);
                    $logic = $this->generate(LogicGen::class, $name, $before);
                    $argvs = [
                        'title' => $this->title,
                        'inheritance' => $logic,
                    ];
                    $this->generate(ControllerGen::class, $name, $before, argv: $argvs, origin: $argvs);
                }
            } else {
                foreach ((array) $variable as $name) {
                    $model = (new ModelGen($this->getAppInput(), $name));
                    $columns = $model->getColumns();
                    $this->title = $model->getTitle();
                    $this->genRequest($name, $before, $columns);
                    $this->genResponse($name, $before, $columns);
                    $argv = $this->handleEnum($errorEnum, $this->title);
                    $this->generate(ConstantGen::class, $name, $before, 'Error', argv: $argv, origin: $argv);
                    $this->generate(LogicGen::class, $name, $before);
                }
            }

            return self::END_SIGNAL;
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage(), 'error');
            if (env('APP_DEV', false)) {
                $this->error($throwable->getTraceAsString(), 'error');
            }
        }
    }

    /**
     * @return array{properties: mixed[]}
     */
    protected function handleEnum(array $enum, string $title): array
    {
        $enums = [];
        foreach ($enum as $key => $item) {
            $item['column_comment'] = sprintf($item['column_comment'], $this->title);
            $enums[$key] = $item;
        }

        return [
            'properties' => $enums,
        ];
    }

    protected function generate(
        $gen,
        string $name,
        string $before,
        ?string $after = null,
        ?array &$args = [],
        ?array &$argv = [],
        ?array $origin = []
    ): string|int {
        $inheritance = $argv && array_key_exists('inheritance', $argv) ? $argv['inheritance'] : null;

        $is_suffix = $argv && array_key_exists('is_suffix', $argv) ? $argv['is_suffix'] : true;
        if ($inheritance) {
            if (! class_exists($inheritance)) {
                $argv['inheritance'] = $this->getClass($gen, $name, $before, $inheritance, $args);
                if (! class_exists($argv['inheritance'])) {
                    $this->generate($gen, $name, $before, null, $args, $args[$inheritance], origin: $origin);
                }
            }
        }
        if ($argv && array_key_exists('suffix', $argv) && $suffix = $argv['suffix']) {
            $name .= $suffix;
        }
        $generate = new $gen(
            $this->getAppInput(),
            $name,
            $before,
            $after,
            is_suffix: $is_suffix,
            inheritance: $argv['inheritance'] ?? ''
        );
        $generate->properties = $argv['properties'] ?? [];
        $generate->title = $this->title ?? '';
        $generate->relation = $this->relation;
        $generate->server = $this->getOptionInput('server', null);
        $generate->prefix = $this->getOptionInput('prefix', null);
        $generate->auth = $this->getOptionInput('auth', true);
        $generate->permission = $this->getOptionInput('permission', true);
        $generate->noPrefix = $this->getOptionInput('no-prefix', false);
        $ignore = $this->config->get('generator.ignore.controller', []);
        if (in_array($this->genName, $ignore)) {
            $file = $generate->getFile();

            $this->fs->exists($file) && $this->fs->delete($file);

            return '';
        }

        $result = $generate->generate();
        $this->info(
            sprintf('%s %s 构建成功', Str::replaceLast(
                'Gen',
                '',
                Arr::last(explode('\\', $generate::class))
            ), $result)
        );
        // dump($this->relation);

        return $result;
    }

    protected function genRequest(string $name, string $before, array $properties): array
    {
        $args = [
            'ListQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'Search' => [
                'is_suffix' => false,
                'properties' => $properties,
                'inheritance' => BaseSearch::class,
            ],
            'CreateQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'CreateData' => [
                'is_suffix' => false,
                'properties' => $properties,
                'inheritance' => BaseRequest::class,
            ],
            'UpdateQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'UpdateData' => [
                'is_suffix' => false,
                'properties' => $properties,
                'inheritance' => BaseRequest::class,
            ],
            'DetailQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'DeleteQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'RecoveryQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'ChangeQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
            'ToggleQuery' => [
                'is_suffix' => false,
                'properties' => [],
                'inheritance' => BaseQuery::class,
            ],
        ];

        foreach ($args as $suffix => &$argv) {
            if (! array_key_exists('properties', $argv)) {
                $argv['properties'] = [];
            }
            foreach ($argv['properties'] as &$property) {
                if ($property['column_type'] == ArrayType::class && ! class_exists($property['data_type'])) {
                    $property['data_type'] = $this->getClass(
                        RequestGen::class,
                        $name,
                        $before,
                        $property['data_type'],
                        $args
                    );
                }
            }
            $argv['suffix'] = $suffix;
            $request = $this->generate(RequestGen::class, $name, $before, null, $args, $argv);
            $this->relation[$suffix] = $request;
            if (array_key_exists('inheritance', $argv) && array_key_exists($argv['inheritance'], $this->relation)) {
                $this->relation[$argv['inheritance']] = $request;
            }
        }

        return $args;
    }

    protected function genResponse(string $name, string $before, array $properties): array
    {
        $itemClass = (new AttributesGen(
            $this->getAppInput(),
            "{$name}Item",
            'Model',
            is_suffix: false
        ))->qualifyClass();
        $args = [
            'List' => [
                'inheritance' => BaseListResponse::class,
                'properties' => [
                    [
                        'column_key' => '',
                        'column_name' => 'data',
                        'data_type' => $itemClass,
                        'column_comment' => '响应数据',
                        'extra' => '',
                        'column_type' => ArrayType::class,
                        'cast' => null,
                    ],
                ],
            ],
            'Detail' => [
                'inheritance' => BaseResponse::class,
                'properties' => [
                    [
                        'column_key' => '',
                        'column_name' => 'data',
                        'data_type' => $itemClass,
                        'column_comment' => '响应数据',
                        'extra' => '',
                        'column_type' => 'array',
                        'cast' => null,
                    ],
                ],
            ],
        ];
        foreach ($args as $suffix => &$argv) {
            if (! array_key_exists('properties', $argv)) {
                $argv['properties'] = [];
            }

            foreach ($argv['properties'] as &$property) {
                if ($property['column_type'] == ArrayType::class && ! class_exists($property['data_type'])) {
                    $dataType = class_exists($property['data_type']) ? $property['data_type'] : $this->getClass(
                        ResponseGen::class,
                        $name,
                        $before,
                        $property['data_type'],
                        $args
                    );
                    $property['data_type'] = 'array';
                    $property['attributes'] = [
                        ArrayType::class => [$dataType],
                    ];
                }
                if (array_key_exists($property['data_type'] ?? '', $args) && ! class_exists($property['data_type'])) {
                    $dataType = class_exists($property['data_type']) ? $property['data_type'] : $this->getClass(
                        ResponseGen::class,
                        $name,
                        $before,
                        $property['data_type'],
                        $args
                    );
                    $property['data_type'] = $dataType;
                }
            }
            $argv['suffix'] = $suffix;
            $response = $this->generate(ResponseGen::class, $name, $before, null, $args, $argv);
            $this->relation["{$suffix}Response"] = $response;
            if (array_key_exists('inheritance', $argv) && array_key_exists($argv['inheritance'], $this->relation)) {
                $this->relation[$argv['inheritance']] = $response;
            }
        }

        return $args;
    }

    protected function configure(): void
    {
        $this->setDescription('创建新的控制器类');
        $this->addOption('prefix', null, InputOption::VALUE_OPTIONAL, '前缀');
        $this->addOption('server', null, InputOption::VALUE_OPTIONAL, 'http服务器', 'http');
        $this->addOption('auth', null, InputOption::VALUE_NEGATABLE, '是否授权', true);
        $this->addOption('permission', null, InputOption::VALUE_NEGATABLE, '是否鉴权', true);
        $this->addOption('no-prefix', null, InputOption::VALUE_NEGATABLE, '路由不添加前缀', false);
        parent::configure();
    }

    private function getClass($gen, string $name, string $before, string $suffix, array $args): string
    {
        $argv = $args[$suffix] ?? [];
        $is_suffix = array_key_exists('is_suffix', $argv) ? $argv['is_suffix'] : true;

        $gen = new $gen($this->getAppInput(), "{$name}{$suffix}", $before, is_suffix: $is_suffix);

        return $gen->qualifyClass();
    }
}
