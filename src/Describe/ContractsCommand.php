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
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[Command]
class ContractsCommand extends HyperfCommand
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
        parent::__construct('describe:contracts');
    }

    public function handle(): void
    {
        try {
            $this->show($this->handleData(), $this->output);
        } catch (Throwable $throwable) {
            $this->error(__METHOD__, 'error');
            $this->error($throwable->getMessage(), 'error');
            $this->error($throwable->getTraceAsString(), 'error');
            $this->error(__METHOD__, 'info');
        }
    }

    protected function configure(): void
    {
        $this->setDescription('Describe the providers and contracts.');
    }

    protected function handleData(): array
    {
        try {
            $config = container()
                ->get(ConfigInterface::class);
            $collector = $config->get('dependencies');
            $data = [];
            foreach ($collector as $contract => $provider) {
                if (is_array($provider)) {
                    $this->handleChildren($provider, $contract, $data);
                } else {
                    $data[$provider]['providers'] = $provider;
                    $data[$provider]['contracts'] = array_merge($data[$provider]['contracts'] ?? [], [$contract]);
                }
            }

            return $data;
        } catch (Throwable $throwable) {
            throw $throwable;
        }
    }

    protected function handleChildren($providers, $parent, &$data): void
    {
        foreach ($providers as $contract => $provider) {
            if (is_array($provider)) {
                $this->handleChildren($provider, $contract, $data);
            } else {
                $data[$provider]['providers'] = $provider;
                $data[$provider]['contracts'] = array_merge(
                    $data[$provider]['contracts'] ?? [],
                    [sprintf('%s.%s', $parent, $contract)]
                );
            }
        }
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
        try {
            $rows = [];
            foreach ($data as $route) {
                $route['contracts'] = implode(PHP_EOL, (array) $route['contracts']);
                $rows[] = $route;
                $rows[] = new TableSeparator();
            }

            $rows = array_slice($rows, 0, count($rows) - 1);
            if ($rows) {
                $table = new Table($output);
                $table->setHeaders(['Provider', 'Interface'])->setRows($rows);
                $table->render();
            }
        } catch (Throwable $throwable) {
            throw $throwable;
        }
    }
}
