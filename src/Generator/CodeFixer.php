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

namespace Mine\Devtool\Generator;

use ArrayIterator;
use PhpCsFixer\Config;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Console\Report\FixReport\ReportSummary;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Runner;

use PhpCsFixer\ToolInfo;
use SplFileInfo;

use Symfony\Component\Stopwatch\Stopwatch;

use function count;

/**
 * @internal
 */
final class CodeFixer
{
    private const CONFIG = __DIR__ . '/../php-cs-fixer-config.php';

    public function fix($file): int
    {
        return 1;
        $resolver = new ConfigurationResolver(
            new Config(),
            [
                'allow-risky' => null,
                'cache-file' => null,
                'config' => self::CONFIG,
                'diff' => null,
                'dry-run' => false,
                'format' => null,
                'path' => [],
                'path-mode' => ConfigurationResolver::PATH_MODE_OVERRIDE,
                'rules' => '@PSR12,@Symfony',
                'show-progress' => null,
                'stop-on-violation' => false,
                'using-cache' => null,
                'verbosity' => null,
            ],
            getcwd(),
            new ToolInfo()
        );
        $reporter = $resolver->getReporter();
        $finder = new ArrayIterator(new SplFileInfo($file));
        $runner = new Runner(
            $finder,
            $resolver->getFixers(),
            $resolver->getDiffer(),
            null,
            new ErrorsManager(),
            $resolver->getLinter(),
            false,
            $resolver->getCacheManager(),
            $resolver->getDirectory(),
            false
        );
        $stopwatch = new Stopwatch();
        $stopwatch->start('fixFiles');
        $changed = $runner->fix();
        $stopwatch->stop('fixFiles');

        $reportSummary = new ReportSummary($changed, is_countable($finder) ? count(
            $finder
        ) : 0, 1, -1, false, $resolver->isDryRun(), false);
        $reporter->generate($reportSummary);

        return 1;
    }
}
