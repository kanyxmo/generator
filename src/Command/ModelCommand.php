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

use Exception;
use Hyperf\Collection\Arr;
use Hyperf\Command\Annotation\Command;
use Hyperf\Database\Commands\ModelOption;
use Hyperf\Database\Connection;
use Hyperf\Database\Schema\Builder;
use Hyperf\Database\Schema\Schema;
use Mine\Devtool\Generator\ModelGen;
use Mine\Model\Model;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class ModelCommand extends GeneratorCommand
{
    protected ?string $name = 'gen:model';

    public function handle(): void
    {
        try {
            $pool = $this->input->getOption('pool');
            $option = new ModelOption();
            $option->setPool($pool)
                ->setPath(
                    $this->getOption('path', '', $pool, (new ModelGen($this->getAppInput(), ''))->getApp('fullPath'))
                )
                ->setPrefix($this->getOption('prefix', 'prefix', $pool, ''))
                ->setInheritance($this->getOption('inheritance', 'commands.gen:model.inheritance', $pool, 'Model'))
                ->setUses($this->getOption('uses', 'commands.gen:model.uses', $pool, Model::class))
                ->setForceCasts($this->getOption('force-casts', 'commands.gen:model.force_casts', $pool, true))
                ->setRefreshFillable(
                    $this->getOption('refresh-fillable', 'commands.gen:model.refresh_fillable', $pool, true)
                )
                ->setTableMapping($this->getOption('table-mapping', 'commands.gen:model.table_mapping', $pool, []))
                ->setIgnoreTables($this->getOption('ignore-tables', 'commands.gen:model.ignore_tables', $pool, []))
                ->setWithComments($this->getOption('with-comments', 'commands.gen:model.with_comments', $pool, true))
                ->setWithIde($this->getOption('with-ide', 'commands.gen:model.with_ide', $pool, false))
                ->setVisitors($this->getOption('visitors', 'commands.gen:model.visitors', $pool, []))
                ->setPropertyCase($this->getOption('property-case', 'commands.gen:model.property_case', $pool));
            $tables = $this->input->getOption('table');
            if (! empty($tables)) {
                if (is_array($tables)) {
                    foreach ($tables as $table) {
                        $result = (new ModelGen($this->getAppInput(), $table, modelOption: $option))->generate();
                        $this->info(
                            sprintf('%s %s 构建成功', Str::replaceLast(
                                'Command',
                                '',
                                Arr::last(explode('\\', static::class))
                            ), $result)
                        );
                    }
                } else {
                    $result = (new ModelGen($this->getAppInput(), $tables, modelOption: $option))->generate();
                    $this->info(
                        sprintf('%s %s 构建成功', Str::replaceLast(
                            'Command',
                            '',
                            Arr::last(explode('\\', static::class))
                        ), $result)
                    );
                }
            } else {
                $this->creates($option);
            }
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('创建新模型类');
        $this->addOption(
            'pool',
            'p',
            InputOption::VALUE_OPTIONAL,
            '您希望模型使用哪个连接池。',
            'default'
        );
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, '要生成模型文件的路径。');
        $this->addOption('force-casts', 'F', InputOption::VALUE_NONE, '强制是否生成模型的强制转换。');
        $this->addOption('prefix', 'P', InputOption::VALUE_OPTIONAL, '您希望模型集的前缀。');
        $this->addOption('inheritance', 'i', InputOption::VALUE_OPTIONAL, '您希望模型扩展的继承。');
        $this->addOption('uses', 'U', InputOption::VALUE_OPTIONAL, '默认类使用Model。');
        $this->addOption('refresh-fillable', 'R', InputOption::VALUE_NONE, '是否为模型生成可填充参数。');
        $this->addOption(
            'table-mapping',
            'M',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            '模型的表映射。'
        );
        $this->addOption(
            'ignore-tables',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            '忽略用于创建模型的表。'
        );
        $this->addOption('with-comments', null, InputOption::VALUE_NONE, '是否生成模型的属性注释。');
        $this->addOption('with-ide', null, InputOption::VALUE_NONE, '是否为模型生成ide文件。');
        $this->addOption(
            'visitors',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'ast遍历器的自定义访问者。'
        );
        $this->addOption(
            'property-case',
            null,
            InputOption::VALUE_OPTIONAL,
            '您要使用哪种属性大小写，0:蛇大小写，1:骆驼大小写。'
        );
        $this->addOption(
            'table',
            'T',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
            '要与模型关联的表',
            []
        );
        $this->addOption('with', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, '包含前缀');
    }

    protected function getSchemaBuilder(string $poolName): Builder|Schema
    {
        /** @var Connection $connection */
        $connection = $this->resolver->connection($poolName);

        return $connection->getSchemaBuilder();
    }

    protected function creates(ModelOption $option): void
    {
        $builder = $this->getSchemaBuilder($option->getPool());
        $tables = [];

        foreach ($builder->getAllTables() as $row) {
            $row = (array) $row;
            $table = reset($row);
            $table = Str::replaceFirst($option->getPrefix(), '', reset($row));
            if (! $this->isIgnoreTable($table, $option)) {
                $tables[] = $table;
            }
        }

        foreach ($tables as $table) {
            $result = (new ModelGen($this->getAppInput(), $table, modelOption: $option))->generate();
            $this->info(
                sprintf('%s %s 构建成功', Str::replaceLast(
                    'Command',
                    '',
                    Arr::last(explode('\\', static::class))
                ), $result)
            );
        }
    }

    protected function isIgnoreTable(string $table, ModelOption $option): bool
    {
        if (in_array($table, $option->getIgnoreTables())) {
            return true;
        }

        $with = $this->getOption('with', '', '', '');
        $with[] = Str::afterLast($this->getAppInput(), '/');
        if ($with && ! Str::startsWith($table, $with)) {
            return true;
        }

        return $table === $this->config->get('databases.migrations', 'migrations');
    }

    protected function getOption(string $name, string $key, string $pool = 'default', $default = null)
    {
        $result = $this->input->getOption($name);
        $nonInput = null;
        if (in_array($name, ['force-casts', 'refresh-fillable', 'with-comments', 'with-ide'])) {
            $nonInput = false;
        }

        if (in_array($name, ['table-mapping', 'ignore-tables', 'visitors'])) {
            $nonInput = [];
        }

        if ($result === $nonInput) {
            $result = $this->config->get(sprintf('databases.%s.%s', $pool, $key), $default);
        }

        return $result;
    }
}
