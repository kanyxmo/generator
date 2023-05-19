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

use Hyperf\Command\Annotation\Command;
use Hyperf\Stringable\Str;
use Mine\Application;
use Mine\Devtool\Generator\AppGen;
use Mine\Vo\AppInfoVo;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

#[Command]
class AppCommand extends GeneratorCommand
{
    protected AppInfoVo $info;

    protected Application $app;

    protected ?string $name = 'gen:app';

    /**
     * 生成应用基础架构.
     */
    public function handle()
    {
        try {
            if (in_array($this->getAppInput(), ['update', 'upgrade'])) {
                return (new AppGen([
                    'name' => 'upgrade',
                    'author' => 'upgrade',
                    'title' => 'upgrade',
                    'description' => 'upgrade',
                    'version' => $this->getOptionInput('v', '1.0.0'),
                ]))->upgrade();
            }

            [$author, $name] = explode('/', (string) $this->getAppInput());
            if (! $name || ! $author) {
                $name = $name ?: $this->ask('请输入应用名称(只能大小写字母)', 'demo');
                $author = $name && $author ?: $this->ask('请输入作者(只能大小写字母)', 'little');
            }

            if (! $title = $this->getOptionInput('title')) {
                $title = $this->ask('请输入应用中文名称', '演示');
            }

            if (! $description = $this->getOptionInput('description')) {
                $description = $this->ask('请输入应用描述', '演示应用');
            }

            if (! $version = $this->getOptionInput('v')) {
                $version = $this->ask('请输入应用版本', '1.0.0');
            }

            $path = $this->getOptionInput('path');

            $app = [
                'name' => $name,
                'author' => $author,
                'title' => $title,
                'description' => $description,
                'version' => $version,
            ];

            if ($result = (new AppGen($app, $path))->create()) {
                $package = Str::kebab($author) . '/' . Str::kebab($name);
                $this->getOptionInput('install') && $this->call('mine:app', [
                    'package' => $package,
                    '--v' => $version,
                    '--option' => 'install'
                ]);
            }

            return $result;
        } catch (Throwable $throwable) {
            $this->error('异常类', 'error');
            $this->error($throwable->getMessage(), 'error');
            $this->error($throwable->getTraceAsString(), 'error');
            $this->error('异常类', 'info');
        }
    }

    protected function configure(): void
    {
        $this->info = new AppInfoVo();
        $this->addOption('title', 't', InputOption::VALUE_OPTIONAL, '应用中文名称');
        $this->addOption('description', 'd', InputOption::VALUE_OPTIONAL, '应用描述');
        $this->addOption('v', null, InputOption::VALUE_OPTIONAL, '应用版本', '1.0.0');
        $this->addOption('path', 'p', InputOption::VALUE_OPTIONAL, '应用路径','src');
        $this->addOption('install', null, InputOption::VALUE_NONE, '创建完成后安装应用');
        $this->setDescription('创建应用');
        $this->addUsage('xmo/demo -t 演示 -d 演示应用 --v 1.0.0 -p app -install');
        parent::configure();
    }
}
