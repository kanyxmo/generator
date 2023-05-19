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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Command]
class ConfigCommand extends HyperfCommand
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ConfigInterface $config
    ) {
        parent::__construct('describe:config');
    }

    public function handle(): void
    {
        $key = $this->input->getOption('key');

        $config = $this->config->get($key);
        $map = [];
        $this->show($this->analyze($config, $key, $map), $this->output);
    }

    protected function configure(): void
    {
        $this->setDescription('Describe the config information.')
            ->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Press key to get the details of the specified key');
    }

    protected function analyze(array $config, string $key, &$map = []): array
    {
        foreach ($config as $k => $value) {
            if (is_int($k)) {
                $nk = $key;
            } else {
                $nk = "{$key}.{$k}";
            }
            if (is_array($value)) {
                $this->analyze($value, $nk, $map);
            } else {
                $map[$nk][] = $value;
            }
        }

        return $map;
    }

    private function show(array $config, ?SymfonyStyle $output): void
    {
        $rows = [];
        foreach ($config as $key => $value) {
            $row['KEY'] = $key;
            $row['value'] = implode(PHP_EOL, (array) $value);
            $rows[] = $row;
            $rows[] = new TableSeparator();
        }

        $rows = array_slice($rows, 0, count($rows) - 1);
        $table = new Table($output);
        $table
            ->setHeaders(['KEY', 'VALUE'])
            ->setRows($rows);
        $table->render();
    }
}
