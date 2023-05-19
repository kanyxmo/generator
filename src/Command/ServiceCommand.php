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
use Mine\Devtool\Generator\ServiceGen;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class ServiceCommand extends GeneratorCommand
{
    protected ?string $name = 'gen:service';

    public function handle()
    {
        try {
            foreach ((array) $this->getOptionInput('name', []) as $name) {
                $result = (new ServiceGen($this->getAppInput(), $name))->generate();
                $this->info(
                    sprintf('%s %s 构建成功', Str::replaceLast(
                        'Command',
                        '',
                        Arr::last(explode('\\', static::class))
                    ), $result)
                );
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
        $this->setDescription('创建新服务类');
        $this->addOption(
            'implement',
            null,
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
            '继承的接口类',
            []
        );
        parent::configure();
    }
}
