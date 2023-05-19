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

namespace Mine\Devtool\Describe;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Event\ListenerData;
use Hyperf\Event\ListenerProvider;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class ListenersCommand extends HyperfCommand
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
        parent::__construct('describe:listeners');
    }

    public function handle(): void
    {
        $events = $this->input->getOption('events');
        $events = $events ? explode(',', (string) $events) : null;

        $listeners = $this->input->getOption('listeners');
        $listeners = $listeners ? explode(',', (string) $listeners) : null;

        $provider = $this->container->get(ListenerProviderInterface::class);
        $this->show($this->handleData($provider, $events, $listeners), $this->output);
    }

    protected function configure(): void
    {
        $this->setDescription('Describe the events and listeners.')
            ->addOption(
                'events',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Get the detail of the specified information by events.'
            )
            ->addOption(
                'listeners',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Get the detail of the specified information by listeners.'
            );
    }

    protected function handleData(ListenerProviderInterface $provider, ?array $events, ?array $listeners): array
    {
        $data = [];
        if (! $provider instanceof ListenerProvider) {
            return $data;
        }

        foreach ($provider->listeners as $listener) {
            if ($listener instanceof ListenerData) {
                $event = $listener->event;
                if (! is_array($listener->listener)) {
                    continue;
                }

                [$object, $method] = $listener->listener;
                $listenerClassName = $object::class;
                if ($events && ! $this->isMatch($event, $events)) {
                    continue;
                }

                if ($listeners && ! $this->isMatch($listenerClassName, $listeners)) {
                    continue;
                }

                $data[$event]['events'] = $listener->event;
                $data[$event]['listeners'] = array_merge($data[$event]['listeners'] ?? [], [
                    implode('::', [$listenerClassName, $method]),
                ]);
            }
        }

        return $data;
    }

    protected function isMatch(string $target, array $keywords = []): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($target, (string) $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function show(array $data, OutputInterface $output): void
    {
        $rows = [];
        foreach ($data as $route) {
            $route['listeners'] = implode(PHP_EOL, (array) $route['listeners']);
            $rows[] = $route;
            $rows[] = new TableSeparator();
        }

        $rows = array_slice($rows, 0, count($rows) - 1);
        $table = new Table($output);
        $table->setHeaders(['Events', 'Listeners'])->setRows($rows);
        $table->render();
    }
}
