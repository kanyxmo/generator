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

use Hyperf\Command\Command;
use Hyperf\Config\Annotation\Value;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Support\Filesystem\Filesystem;
use Hyperf\Utils\{ApplicationContext, Arr, Str};
use Mine\Application;
use PhpParser\{Error, Lexer, Lexer\Emulative, NodeTraverser, Parser, ParserFactory, PrettyPrinterAbstract, PrettyPrinter\Standard};
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

abstract class GeneratorCommand extends Command
{
    /**
     * @var string
     */
    #[Value('cache.default.prefix')]
    protected string $prefix;

    #[Inject()]
    protected CacheInterface $cache;

    #[Inject()]
    protected Filesystem $fs;

    #[Inject()]
    protected Application $app;

    protected ?ConfigInterface $config = null;

    protected ConnectionResolverInterface $resolver;

    protected Lexer $lexer;

    protected Parser $parser;

    protected Standard $printer;

    protected const END_SIGNAL = 0;

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->resolver = container()
            ->get(ConnectionResolverInterface::class);
        $this->config = container()
            ->get(ConfigInterface::class);
        $this->lexer = new Emulative([
            'usedAttributes' => ['comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos'],
        ]);

        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7, $this->lexer);
        $this->printer = new Standard();

        return parent::run($input, $output);
    }

    abstract public function handle();

    protected function getOptionInput(string $name, $default = '')
    {
        return $this->input->hasOption($name) ? $this->input->getOption($name) : $default;
    }

    protected function getArgumentInput(string $name, $default = '')
    {
        return $this->input->hasArgument($name) ? $this->input->getArgument($name) : $default;
    }

    protected function getAppInput()
    {
        return $this->input->getArgument('app');
    }

    protected function getArguments(): array
    {
        return [['app', InputArgument::REQUIRED, '应用名称']];
    }

    protected function getOptions(): array
    {
        return [
            ['name', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, '要生成的类名', []],
            ['preview', '', InputOption::VALUE_NONE, '预览'],
        ];
    }
}
