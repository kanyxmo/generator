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

use Hyperf\Collection\Arr;
use Hyperf\Command\Annotation\Command;
use Hyperf\Support\Composer;
use Hyperf\Support\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class VendorPublishCommand extends SymfonyCommand
{
    protected ?OutputInterface $output = null;

    protected bool $force = false;

    public function __construct(
        protected Filesystem $filesystem
    ) {
        parent::__construct('vendor:publish');
    }

    protected function configure(): void
    {
        $this->setDescription('Publish any publishable configs from vendor packages.')
            ->addArgument('package', InputArgument::REQUIRED, 'The package file you want to publish.')
            ->addOption('id', 'i', InputOption::VALUE_OPTIONAL, 'The id of the package you want to publish.', null)
            ->addOption('show', 's', InputOption::VALUE_OPTIONAL, 'Show all packages can be publish.', false)
            ->addOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Overwrite any existing files', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->force = $input->getOption('force') !== false;
        $package = $input->getArgument('package');
        $show = $input->getOption('show') !== false;
        $id = $input->getOption('id');

        $extra = Composer::getMergedExtra()[$package] ?? null;
        if (empty($extra)) {
            $output->writeln(sprintf('<fg=red>package [%s] misses `extra` field in composer.json.</>', $package));

            return SIGTERM;
        }

        $provider = Arr::get($extra, 'hyperf.config');
        $config = (new $provider())();

        $publish = Arr::get($config, 'publish');
        if (empty($publish)) {
            $output->writeln(sprintf('<fg=red>No file can be published from package [%s].</>', $package));

            return SIGTERM;
        }

        if ($show) {
            foreach ($publish as $item) {
                $out = '';
                foreach ($item as $key => $value) {
                    $out .= sprintf('%s: %s', $key, $value) . PHP_EOL;
                }

                $output->writeln(sprintf('<fg=green>%s</>', $out));
            }

            return 0;
        }

        if ($id) {
            $item = Arr::where($publish, static fn ($item): bool => $item['id'] == $id);

            if (empty($item)) {
                $output->writeln(sprintf('<fg=red>No file can be published from [%s].</>', $id));

                return SIGTERM;
            }

            return $this->copy($package, $item);
        }

        return $this->copy($package, $publish);
    }

    protected function copy($package, $items): int
    {
        foreach ($items as $item) {
            if (! isset($item['id'], $item['source'], $item['destination'])) {
                continue;
            }

            $id = $item['id'];
            $source = $item['source'];
            $destination = $item['destination'];

            if (! $this->force && $this->filesystem->exists($destination)) {
                $this->output->writeln(sprintf('<fg=red>[%s] already exists.</>', $destination));

                continue;
            }

            if (! $this->filesystem->exists($dirname = dirname((string) $destination))) {
                $this->filesystem->makeDirectory($dirname, 0755, true);
            }

            if ($this->filesystem->isDirectory($source)) {
                $this->filesystem->copyDirectory($source, $destination);
            } else {
                $this->filesystem->copy($source, $destination);
            }

            $this->output->writeln(sprintf('<fg=green>[%s] publishes [%s] successfully.</>', $package, $id));
        }

        return 0;
    }
}
