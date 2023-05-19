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

namespace Mine\Devtool;

use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class InfoCommand extends SymfonyCommand
{
    public function __construct(
        private readonly Info $info
    ) {
        parent::__construct('info');
    }

    protected function configure(): void
    {
        $this->setDescription('Dump the server info.')
            ->addArgument('type', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        if (! $this->info->has($type)) {
            $output->writeln(sprintf('<error>Error</error> Info type [%s] not exist.', $type));

            return 0;
        }

        $adapter = $this->info->get($type);
        $adapter->execute($input, $output);

        return 0;
    }
}
