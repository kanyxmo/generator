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

use PhpCsFixer\Config;
use PhpCsFixer\ConfigInterface;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Output\NullOutput;
use PhpCsFixer\Console\Report\FixReport\ReportSummary;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Runner;
use PhpCsFixer\ToolInfo;
use PhpCsFixer\ToolInfoInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use function count;

/**
 * @internal
 */
// #[AsCommand(name: 'fix')]
final class FixCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'fix';

    private readonly ErrorsManager $errorsManager;

    private readonly Stopwatch $stopwatch;

    private readonly ConfigInterface $defaultConfig;

    private readonly ToolInfoInterface $toolInfo;

    public function __construct()
    {
        parent::__construct();

        $this->errorsManager = new ErrorsManager();
        $this->defaultConfig = new Config();
        $this->stopwatch = new Stopwatch();
        $this->toolInfo = new ToolInfo();
    }

    /**
     * {@inheritdoc}
     *
     * Override here to only generate the help copy when used.
     */
    public function getHelp(): string
    {
        return 'fix';
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition(
                [
                    new InputArgument('path', InputArgument::IS_ARRAY, 'The path.'),
                    new InputOption(
                        'path-mode',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'Specify path mode (can be override or intersection).',
                        ConfigurationResolver::PATH_MODE_OVERRIDE
                    ),
                    new InputOption(
                        'allow-risky',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'Are risky fixers allowed (can be yes or no).'
                    ),
                    new InputOption('config', '', InputOption::VALUE_REQUIRED, 'The path to a .php-cs-fixer.php file.'),
                    new InputOption(
                        'dry-run',
                        '',
                        InputOption::VALUE_NONE,
                        'Only shows which files would have been modified.'
                    ),
                    new InputOption('rules', '', InputOption::VALUE_REQUIRED, 'The rules.'),
                    new InputOption(
                        'using-cache',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'Does cache should be used (can be yes or no).'
                    ),
                    new InputOption('cache-file', '', InputOption::VALUE_REQUIRED, 'The path to the cache file.'),
                    new InputOption('diff', '', InputOption::VALUE_NONE, 'Also produce diff for each file.'),
                    new InputOption('format', '', InputOption::VALUE_REQUIRED, 'To output results in other formats.'),
                    new InputOption(
                        'stop-on-violation',
                        '',
                        InputOption::VALUE_NONE,
                        'Stop execution on first violation.'
                    ),
                    new InputOption(
                        'show-progress',
                        '',
                        InputOption::VALUE_REQUIRED,
                        'Type of progress indicator (none, dots).'
                    ),
                ]
            )
            ->setDescription('Fixes a directory or a file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $passedConfig = $input->getOption('config');
        $passedRules = $input->getOption('rules');
        $resolver = new ConfigurationResolver(
            $this->defaultConfig,
            [
                'allow-risky' => $input->getOption('allow-risky'),
                'config' => $passedConfig,
                'dry-run' => false,
                'rules' => $passedRules,
                'path' => $input->getArgument('path'),
                'path-mode' => $input->getOption('path-mode'),
                'using-cache' => null,
                'cache-file' => null,
                'format' => null,
                'diff' => false,
                'stop-on-violation' => false,
                'verbosity' => null,
                'show-progress' => null,
            ],
            getcwd(),
            $this->toolInfo
        );
        $reporter = $resolver->getReporter();
        $progressOutput = new NullOutput();
        $finder = $resolver->getFinder();
        $runner = new Runner(
            $finder,
            $resolver->getFixers(),
            $resolver->getDiffer(),
            null,
            $this->errorsManager,
            $resolver->getLinter(),
            $resolver->isDryRun(),
            $resolver->getCacheManager(),
            $resolver->getDirectory(),
            $resolver->shouldStopOnViolation()
        );

        $this->stopwatch->start('fixFiles');
        $changed = $runner->fix();
        $this->stopwatch->stop('fixFiles');

        $fixEvent = $this->stopwatch->getEvent('fixFiles');

        $reportSummary = new ReportSummary(
            $changed,
            is_countable($finder) ? count($finder) : 0,
            $fixEvent->getDuration(),
            $fixEvent->getMemory(),
            false,
            $resolver->isDryRun(),
            $output->isDecorated()
        );
        $reporter->generate($reportSummary);

        return 1;
    }
}
