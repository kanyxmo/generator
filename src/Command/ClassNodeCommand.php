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
use Hyperf\HttpServer\Annotation\{Controller, DeleteMapping, GetMapping, PostMapping, PutMapping, RequestMapping};
use Mine\Annotation\{Auth, Operation, Permission};
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Symfony\Component\Console\Input\InputOption;

#[Command]
class ClassNodeCommand extends GeneratorCommand
{
    protected ?string $name = 'gen:node';

    protected array $relation = [];

    protected string $title = '';

    public function handle(): void
    {
    }

    protected function configure(): void
    {
        $this->setDescription('给类添加一个节点，方法，属性，常量');
        $this->addOption('controller', null, InputOption::VALUE_OPTIONAL, '控制器节点');
        $this->addOption('server', null, InputOption::VALUE_OPTIONAL, '', '服务节点');
        parent::configure();
    }
}
