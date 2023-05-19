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

namespace Mine\Devtool\Adapter;

use Hyperf\Di\Annotation\AspectCollector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Aspects extends AbstractAdapter
{
    public function execute(InputInterface $input, OutputInterface $output): void
    {
        $result = $this->prepareResult();
        $this->dump($result, $output);
    }

    /**
     * Prepare the result, maybe this result not just use in here.
     */
    public function prepareResult(): array
    {
        $result = [];
        $aspects = AspectCollector::list();
        foreach ($aspects as $type => $collections) {
            foreach ($collections as $aspect => $target) {
                $result[$aspect][$type] = $target;
            }
        }

        return $result;
    }

    /**
     * Dump to the console according to the prepared result.
     */
    private function dump(array $result, OutputInterface $output): void
    {
        foreach ($result as $aspect => $targets) {
            $output->writeln(sprintf('<info>%s</info>', $aspect));
            if (isset($targets['annotations'])) {
                $output->writeln($this->tab('Annotations:'));
                foreach ($targets['annotations'] ?? [] as $annotation) {
                    $output->writeln($this->tab($annotation ?? '', 2));
                }
            }

            if (isset($targets['classes'])) {
                $output->writeln($this->tab('Classes:'));
                foreach ($targets['classes'] ?? [] as $class) {
                    $output->writeln($this->tab($class ?? '', 2));
                }
            }
        }
    }
}
