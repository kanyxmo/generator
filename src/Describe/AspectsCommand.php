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
use Hyperf\Di\Annotation\AspectCollector;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class AspectsCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('describe:aspects');
    }

    public function handle(): void
    {
        $classes = $this->input->getOption('classes');
        $classes = $classes ? explode(',', (string) $classes) : null;

        $aspects = $this->input->getOption('aspects');
        $aspects = $aspects ? explode(',', (string) $aspects) : null;

        $collector = AspectCollector::list();
        $this->show('Classes', $this->handleData($collector['classes'], $classes, $aspects), $this->output);
        $this->show('Annotations', $this->handleData($collector['annotations'], $classes, $aspects), $this->output);
    }

    protected function configure(): void
    {
        $this->setDescription('Describe the aspects.')
            ->addOption(
                'classes',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Get the detail of the specified information by classes.'
            )
            ->addOption(
                'aspects',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Get the detail of the specified information by aspects.'
            );
    }

    protected function handleData(array $collector, ?array $classes, ?array $aspects): array
    {
        $data = [];
        foreach ($collector as $aspect => $targets) {
            foreach ($targets as $target) {
                if ($classes && ! $this->isMatch($target, $classes)) {
                    continue;
                }

                if ($aspects && ! $this->isMatch($aspect, $aspects)) {
                    continue;
                }

                $data[$target]['targets'] = $target;
                $data[$target]['aspects'] = array_merge($data[$target]['aspects'] ?? [], [$aspect]);
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

    protected function show(string $title, array $data, OutputInterface $output): void
    {
        $rows = [];
        foreach ($data as $route) {
            $route['aspects'] = implode(PHP_EOL, (array) $route['aspects']);
            $rows[] = $route;
            $rows[] = new TableSeparator();
        }

        $rows = array_slice($rows, 0, count($rows) - 1);
        if ($rows) {
            $table = new Table($output);
            $table->setHeaders([$title, 'Aspects'])->setRows($rows);
            $table->render();
        }
    }
}
