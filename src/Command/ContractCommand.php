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
use Hyperf\Di\ReflectionManager;
use Hyperf\Stringable\Str;
use Mine\Devtool\Generator\ContractGen;
use ReflectionClass;

#[Command]
class ContractCommand extends GeneratorCommand
{
    protected ?string $name = 'gen:contract';

    public function handle()
    {
        try {
            $variable = $this->getOptionInput('name');

            if (! $variable) {
                $fullPath = [];
                if (in_array($this->getAppInput(), ['update', 'upgrade'])) {
                    $apps = $this->app->getAppInfo();
                    foreach ($apps as $app) {
                        $fullPath = [];
                        if ($app['fullPath'] ?? false) {
                            $fullPath[] = $app['fullPath'] . '/Contract';
                            $reflections = ReflectionManager::getAllClasses($fullPath);
                            foreach ($reflections as $reflection) {
                                assert($reflection instanceof ReflectionClass);
                                $generate = new ContractGen($app['name'], Str::replaceLast(
                                    'Interface',
                                    '',
                                    $reflection->getShortName()
                                ));
                                $result = $generate->generate();
                                $this->info(
                                    sprintf('%s %s %s 构建成功', $app['name'], Str::replaceLast(
                                        'Gen',
                                        '',
                                        Arr::last(explode('\\', $generate::class))
                                    ), $result)
                                );
                            }
                            $deleted = ['Annotation', 'Aspect', 'Command', 'Factory', 'Provider'];
                            foreach ($deleted as $item) {
                                $dir = $app['fullPath'] . $item;
                                if ($this->fs->isDirectory($dir)) {
                                    $this->fs->deleteDirectory($dir);
                                }
                            }
                        }
                    }
                } else {
                    $fullPath[] = (new ContractGen($this->getAppInput(), ''))->getApp('fullPath');
                    $reflections = ReflectionManager::getAllClasses($fullPath);
                    foreach ($reflections as $reflection) {
                        assert($reflection instanceof ReflectionClass);
                        $generate = new ContractGen($this->getAppInput(), Str::replaceLast(
                            'Interface',
                            '',
                            $reflection->getShortName()
                        ));
                        $result = $generate->generate();
                        $this->info(
                            sprintf('%s %s 构建成功', Str::replaceLast(
                                'Gen',
                                '',
                                Arr::last(explode('\\', $generate::class))
                            ), $result)
                        );
                    }
                }
            } else {
                foreach ((array) $variable as $name) {
                    $generate = new ContractGen($this->getAppInput(), $name);
                    $result = $generate->generate();
                    $this->info(
                        sprintf('%s %s %s 构建成功', $this->getAppInput(), Str::replaceLast(
                            'Gen',
                            '',
                            Arr::last(explode('\\', $generate::class))
                        ), $result)
                    );
                }
            }

            return self::END_SIGNAL;
        } catch (Exception $exception) {
            $this->error('异常类', 'error');
            $this->error($exception->getMessage(), 'error');
            $this->error($exception->getTraceAsString(), 'error');
            $this->error('异常类', 'info');
        }
    }

    protected function configure(): void
    {
        $this->setDescription('创建新的契约类');
        parent::configure();
    }
}
